/**
 * A minimal, dependency-free ZIP reader/writer (SPEC §6.6, Decision "Zip writer"). No new
 * dependency: `node:zlib`'s `deflateRawSync`/`inflateRawSync`/`crc32` do the compression and
 * checksum work; this module only assembles/parses the ZIP container format itself. Every
 * timestamp is fixed at 1980-01-01 00:00 (DOS epoch) and entries are written in sorted-name
 * order, so two packs of the same input are byte-identical.
 */

import { crc32, deflateRawSync, inflateRawSync } from 'node:zlib';

const LOCAL_FILE_HEADER_SIG = 0x04034b50;
const CENTRAL_DIR_SIG = 0x02014b50;
const END_OF_CENTRAL_DIR_SIG = 0x06054b50;

// DOS date/time for 1980-01-01 00:00:00: date bits are (year-1980)<<9 | month<<5 | day, i.e.
// (0<<9) | (1<<5) | 1 = 0x0021; time bits (hour<<11 | minute<<5 | second/2) are all zero.
const DOS_TIME = 0x0000;
const DOS_DATE = 0x0021;

const METHOD_STORED = 0;
const METHOD_DEFLATE = 8;

/**
 * One entry to write: `name` is the ZIP-internal path (forward slashes), `data` its bytes.
 *
 * @typedef {{name: string, data: Buffer}} ZipEntry
 */

/**
 * Ordinary string comparison, without a nested ternary.
 *
 * @param {string} a First name.
 * @param {string} b Second name.
 * @return {number} -1, 0 or 1.
 */
function compareNames( a, b ) {
	if ( a < b ) {
		return -1;
	}
	if ( a > b ) {
		return 1;
	}
	return 0;
}

/**
 * Build a deterministic ZIP archive.
 *
 * @param {ZipEntry[]} entries Entries (sorted here regardless of input order).
 * @return {Buffer} The ZIP file's bytes.
 */
export function writeZip( entries ) {
	const sorted = [ ...entries ].sort( ( a, b ) =>
		compareNames( a.name, b.name )
	);

	const localParts = [];
	const centralParts = [];
	let offset = 0;

	for ( const entry of sorted ) {
		const nameBuffer = Buffer.from( entry.name, 'utf8' );
		const deflated = deflateRawSync( entry.data );
		const useDeflate = deflated.length < entry.data.length;
		const method = useDeflate ? METHOD_DEFLATE : METHOD_STORED;
		const payload = useDeflate ? deflated : entry.data;
		const crc = crc32( entry.data );

		const localHeader = Buffer.alloc( 30 );
		localHeader.writeUInt32LE( LOCAL_FILE_HEADER_SIG, 0 );
		localHeader.writeUInt16LE( 20, 4 ); // version needed
		localHeader.writeUInt16LE( 0, 6 ); // flags
		localHeader.writeUInt16LE( method, 8 );
		localHeader.writeUInt16LE( DOS_TIME, 10 );
		localHeader.writeUInt16LE( DOS_DATE, 12 );
		localHeader.writeUInt32LE( crc, 14 );
		localHeader.writeUInt32LE( payload.length, 18 );
		localHeader.writeUInt32LE( entry.data.length, 22 );
		localHeader.writeUInt16LE( nameBuffer.length, 26 );
		localHeader.writeUInt16LE( 0, 28 ); // extra field length

		localParts.push( localHeader, nameBuffer, payload );

		const centralHeader = Buffer.alloc( 46 );
		centralHeader.writeUInt32LE( CENTRAL_DIR_SIG, 0 );
		centralHeader.writeUInt16LE( 20, 4 ); // version made by
		centralHeader.writeUInt16LE( 20, 6 ); // version needed
		centralHeader.writeUInt16LE( 0, 8 ); // flags
		centralHeader.writeUInt16LE( method, 10 );
		centralHeader.writeUInt16LE( DOS_TIME, 12 );
		centralHeader.writeUInt16LE( DOS_DATE, 14 );
		centralHeader.writeUInt32LE( crc, 16 );
		centralHeader.writeUInt32LE( payload.length, 20 );
		centralHeader.writeUInt32LE( entry.data.length, 24 );
		centralHeader.writeUInt16LE( nameBuffer.length, 28 );
		centralHeader.writeUInt16LE( 0, 30 ); // extra field length
		centralHeader.writeUInt16LE( 0, 32 ); // comment length
		centralHeader.writeUInt16LE( 0, 34 ); // disk number start
		centralHeader.writeUInt16LE( 0, 36 ); // internal attrs
		centralHeader.writeUInt32LE( 0, 38 ); // external attrs
		centralHeader.writeUInt32LE( offset, 42 ); // local header offset

		centralParts.push( centralHeader, nameBuffer );

		offset += localHeader.length + nameBuffer.length + payload.length;
	}

	const centralDir = Buffer.concat( centralParts );
	const centralDirOffset = offset;

	const eocd = Buffer.alloc( 22 );
	eocd.writeUInt32LE( END_OF_CENTRAL_DIR_SIG, 0 );
	eocd.writeUInt16LE( 0, 4 ); // disk number
	eocd.writeUInt16LE( 0, 6 ); // disk with central dir
	eocd.writeUInt16LE( sorted.length, 8 ); // entries on this disk
	eocd.writeUInt16LE( sorted.length, 10 ); // total entries
	eocd.writeUInt32LE( centralDir.length, 12 );
	eocd.writeUInt32LE( centralDirOffset, 16 );
	eocd.writeUInt16LE( 0, 20 ); // comment length

	return Buffer.concat( [ ...localParts, centralDir, eocd ] );
}

/**
 * Read a ZIP archive back into its entries, via the central directory (not by scanning local
 * headers, which is the format's own canonical source of truth for entry boundaries).
 *
 * @param {Buffer} buffer ZIP file bytes.
 * @return {ZipEntry[]} Entries, in central-directory order.
 */
export function readZip( buffer ) {
	let eocdOffset = -1;
	for ( let i = buffer.length - 22; i >= 0; i-- ) {
		if ( buffer.readUInt32LE( i ) === END_OF_CENTRAL_DIR_SIG ) {
			eocdOffset = i;
			break;
		}
	}
	if ( -1 === eocdOffset ) {
		throw new Error(
			'Not a valid ZIP file (no end-of-central-directory record)'
		);
	}

	const totalEntries = buffer.readUInt16LE( eocdOffset + 10 );
	const centralDirOffset = buffer.readUInt32LE( eocdOffset + 16 );

	const entries = [];
	let pointer = centralDirOffset;

	for ( let i = 0; i < totalEntries; i++ ) {
		if ( buffer.readUInt32LE( pointer ) !== CENTRAL_DIR_SIG ) {
			throw new Error(
				`Malformed central directory entry at offset ${ pointer }`
			);
		}
		const method = buffer.readUInt16LE( pointer + 10 );
		const compressedSize = buffer.readUInt32LE( pointer + 20 );
		const uncompressedSize = buffer.readUInt32LE( pointer + 24 );
		const nameLength = buffer.readUInt16LE( pointer + 28 );
		const extraLength = buffer.readUInt16LE( pointer + 30 );
		const commentLength = buffer.readUInt16LE( pointer + 32 );
		const localHeaderOffset = buffer.readUInt32LE( pointer + 42 );
		const name = buffer
			.subarray( pointer + 46, pointer + 46 + nameLength )
			.toString( 'utf8' );

		const localNameLength = buffer.readUInt16LE( localHeaderOffset + 26 );
		const localExtraLength = buffer.readUInt16LE( localHeaderOffset + 28 );
		const dataStart =
			localHeaderOffset + 30 + localNameLength + localExtraLength;
		const compressed = buffer.subarray(
			dataStart,
			dataStart + compressedSize
		);
		const data =
			METHOD_DEFLATE === method
				? inflateRawSync( compressed )
				: Buffer.from( compressed );

		if ( data.length !== uncompressedSize ) {
			throw new Error( `${ name }: decompressed size mismatch` );
		}

		entries.push( { name, data } );
		pointer += 46 + nameLength + extraLength + commentLength;
	}

	return entries;
}

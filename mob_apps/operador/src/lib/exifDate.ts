/**
 * Extrae la fecha original de una foto JPEG desde sus metadatos EXIF
 * (tag DateTimeOriginal 0x9003, o DateTime 0x0132 como respaldo).
 * Devuelve null si el archivo no es JPEG o no trae esos metadatos —
 * en ese caso el llamador debe usar la fecha de subida en su lugar.
 */
export async function readExifDate(file: File): Promise<Date | null> {
  if (file.type !== 'image/jpeg' && file.type !== 'image/jpg') return null;

  const buffer = await file.slice(0, 128 * 1024).arrayBuffer();
  const view = new DataView(buffer);

  if (view.getUint16(0) !== 0xffd8) return null; // no es JPEG (SOI marker)

  let offset = 2;
  while (offset + 4 <= view.byteLength) {
    const marker = view.getUint16(offset);
    if ((marker & 0xff00) !== 0xff00) break;

    const segmentLength = view.getUint16(offset + 2);
    if (marker === 0xffe1) {
      const exifStart = offset + 4;
      if (
        view.getUint32(exifStart) === 0x45786966 && // "Exif"
        view.getUint16(exifStart + 4) === 0x0000
      ) {
        const date = parseExifSegment(view, exifStart + 6);
        if (date) return date;
      }
    }
    if (marker === 0xffda) break; // Start of Scan — ya no hay más metadatos
    offset += 2 + segmentLength;
  }

  return null;
}

function parseExifSegment(view: DataView, tiffStart: number): Date | null {
  const byteOrder = view.getUint16(tiffStart);
  const little = byteOrder === 0x4949; // "II"
  if (!little && byteOrder !== 0x4d4d) return null; // ni "II" ni "MM"

  const readU16 = (o: number) => view.getUint16(o, little);
  const readU32 = (o: number) => view.getUint32(o, little);

  const ifd0Offset = tiffStart + readU32(tiffStart + 4);
  let exifIfdOffset: number | null = null;

  const entries0 = readU16(ifd0Offset);
  for (let i = 0; i < entries0; i++) {
    const entryOffset = ifd0Offset + 2 + i * 12;
    if (readU16(entryOffset) === 0x8769) { // Exif IFD pointer
      exifIfdOffset = tiffStart + readU32(entryOffset + 8);
      break;
    }
  }
  if (exifIfdOffset === null) return null;

  const entriesExif = readU16(exifIfdOffset);
  for (let i = 0; i < entriesExif; i++) {
    const entryOffset = exifIfdOffset + 2 + i * 12;
    const tag = readU16(entryOffset);
    if (tag === 0x9003 || tag === 0x0132) { // DateTimeOriginal / DateTime
      const valueOffset = entryOffset + 8;
      let str = '';
      for (let b = 0; b < 19; b++) str += String.fromCharCode(view.getUint8(valueOffset + b));
      return parseExifDateString(str);
    }
  }

  return null;
}

function parseExifDateString(str: string): Date | null {
  // Formato EXIF: "YYYY:MM:DD HH:MM:SS"
  const m = str.match(/^(\d{4}):(\d{2}):(\d{2}) (\d{2}):(\d{2}):(\d{2})/);
  if (!m) return null;
  const [, y, mo, d, h, mi, s] = m;
  const date = new Date(Number(y), Number(mo) - 1, Number(d), Number(h), Number(mi), Number(s));
  return Number.isNaN(date.getTime()) ? null : date;
}

'use strict';
const TARGET_DPI=300, METERS_PER_INCH=39.37007874015748;
        function loadImage(file) {
            return new Promise((resolve, reject) => {
                const image = new Image();
                const url = URL.createObjectURL(file);
                image.onload = () => {
                    URL.revokeObjectURL(url);
                    resolve(image);
                };
                image.onerror = () => {
                    URL.revokeObjectURL(url);
                    reject(new Error('Image could not be loaded'));
                };
                image.src = url;
            });
        }

        function drawImageToCanvas(image) {
            const canvas = document.createElement('canvas');
            canvas.width = image.naturalWidth || image.width;
            canvas.height = image.naturalHeight || image.height;
            canvas.getContext('2d').drawImage(image, 0, 0);
            return canvas;
        }

        function cloneCanvas(canvas) {
            const clone = document.createElement('canvas');
            clone.width = canvas.width;
            clone.height = canvas.height;
            clone.getContext('2d').drawImage(canvas, 0, 0);
            return clone;
        }

        function hasMeaningfulTransparency(data) {
            for (let index = 3; index < data.length; index += 4) {
                if (data[index] < 245) {
                    return true;
                }
            }
            return false;
        }

        function sampleCornerColor(imageData, width, height) {
            const data = imageData.data;
            const size = Math.min(width, height, Math.max(1, Math.floor(Math.min(width, height) * 0.04)));
            const colors = [];
            const regions = [
                [0, 0],
                [width - size, 0],
                [0, height - size],
                [width - size, height - size],
            ];

            for (const [startX, startY] of regions) {
                for (let y = startY; y < startY + size; y += 1) {
                    for (let x = startX; x < startX + size; x += 1) {
                        const index = (y * width + x) * 4;
                        if (data[index + 3] > 245) {
                            colors.push([data[index], data[index + 1], data[index + 2]]);
                        }
                    }
                }
            }

            if (!colors.length) {
                return [255, 255, 255];
            }

            return colors.reduce((avg, color) => {
                avg[0] += color[0] / colors.length;
                avg[1] += color[1] / colors.length;
                avg[2] += color[2] / colors.length;
                return avg;
            }, [0, 0, 0]);
        }

        function removeBackground(imageData, bg, tolerance) {
            const data = new Uint8ClampedArray(imageData.data);
            const width = imageData.width;
            const height = imageData.height;
            const total = width * height;
            const visited = new Uint8Array(total);
            const queue = new Int32Array(total);
            const feather = 10;
            let head = 0;
            let tail = 0;

            const enqueueIfBackground = (pixelIndex) => {
                if (visited[pixelIndex]) {
                    return;
                }
                const dataIndex = pixelIndex * 4;
                if (data[dataIndex + 3] < 8) {
                    visited[pixelIndex] = 1;
                    queue[tail] = pixelIndex;
                    tail += 1;
                    return;
                }
                const distance = colorDistance(data[dataIndex], data[dataIndex + 1], data[dataIndex + 2], bg[0], bg[1], bg[2]);
                if (distance <= tolerance + feather) {
                    visited[pixelIndex] = 1;
                    queue[tail] = pixelIndex;
                    tail += 1;
                }
            };

            for (let x = 0; x < width; x += 1) {
                enqueueIfBackground(x);
                enqueueIfBackground((height - 1) * width + x);
            }

            for (let y = 1; y < height - 1; y += 1) {
                enqueueIfBackground(y * width);
                enqueueIfBackground(y * width + width - 1);
            }

            while (head < tail) {
                const pixelIndex = queue[head];
                head += 1;
                const x = pixelIndex % width;
                const y = Math.floor(pixelIndex / width);
                const dataIndex = pixelIndex * 4;
                const distance = colorDistance(data[dataIndex], data[dataIndex + 1], data[dataIndex + 2], bg[0], bg[1], bg[2]);

                if (data[dataIndex + 3] < 8 || distance <= tolerance) {
                    data[dataIndex + 3] = 0;
                } else {
                    data[dataIndex + 3] = Math.min(data[dataIndex + 3], Math.round(((distance - tolerance) / feather) * 255));
                }

                if (x > 0) {
                    enqueueIfBackground(pixelIndex - 1);
                }
                if (x < width - 1) {
                    enqueueIfBackground(pixelIndex + 1);
                }
                if (y > 0) {
                    enqueueIfBackground(pixelIndex - width);
                }
                if (y < height - 1) {
                    enqueueIfBackground(pixelIndex + width);
                }
            }

            return new ImageData(data, imageData.width, imageData.height);
        }

        function colorDistance(r1, g1, b1, r2, g2, b2) {
            return Math.sqrt((r1 - r2) ** 2 + (g1 - g2) ** 2 + (b1 - b2) ** 2);
        }

        function findAlphaBounds(imageData, width, height) {
            const data = imageData.data;
            let minX = width;
            let minY = height;
            let maxX = -1;
            let maxY = -1;

            for (let y = 0; y < height; y += 1) {
                for (let x = 0; x < width; x += 1) {
                    const alpha = data[(y * width + x) * 4 + 3];
                    if (alpha > 0) {
                        minX = Math.min(minX, x);
                        minY = Math.min(minY, y);
                        maxX = Math.max(maxX, x);
                        maxY = Math.max(maxY, y);
                    }
                }
            }

            if (maxX < minX || maxY < minY) {
                return { x: 0, y: 0, width, height };
            }

            const pad = 2;
            minX = Math.max(0, minX - pad);
            minY = Math.max(0, minY - pad);
            maxX = Math.min(width - 1, maxX + pad);
            maxY = Math.min(height - 1, maxY + pad);
            return { x: minX, y: minY, width: maxX - minX + 1, height: maxY - minY + 1 };
        }

        function cropToBounds(imageData, width, height, bounds) {
            const source = document.createElement('canvas');
            source.width = width;
            source.height = height;
            source.getContext('2d').putImageData(imageData, 0, 0);

            const canvas = document.createElement('canvas');
            canvas.width = bounds.width;
            canvas.height = bounds.height;
            canvas.getContext('2d').drawImage(source, bounds.x, bounds.y, bounds.width, bounds.height, 0, 0, bounds.width, bounds.height);
            return canvas;
        }

        function scaleCanvas(canvas, scale) {
            if (scale <= 1.01) {
                return canvas;
            }
            const output = document.createElement('canvas');
            output.width = Math.max(1, Math.round(canvas.width * scale));
            output.height = Math.max(1, Math.round(canvas.height * scale));
            const context = output.getContext('2d');
            context.imageSmoothingEnabled = true;
            context.imageSmoothingQuality = 'high';
            context.drawImage(canvas, 0, 0, output.width, output.height);
            return output;
        }

        function appearsToHaveEdgeBackground(imageData, width, height) {
            const data = imageData.data;
            let opaqueEdges = 0;
            let checked = 0;
            const scan = Math.max(1, Math.floor(Math.min(width, height) * 0.04));

            for (let y = 0; y < height; y += 1) {
                for (let x = 0; x < width; x += 1) {
                    if (x >= scan && x < width - scan && y >= scan && y < height - scan) {
                        continue;
                    }
                    checked += 1;
                    if (data[(y * width + x) * 4 + 3] > 245) {
                        opaqueEdges += 1;
                    }
                }
            }
            return checked > 0 && opaqueEdges / checked > 0.72;
        }

        function canvasToPngBlob(canvas) {
            return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        }

        async function addPngDpi(blob, pixelsPerMeter) {
            const bytes = new Uint8Array(await blob.arrayBuffer());
            const signature = bytes.slice(0, 8);
            const chunks = [];
            let offset = 8;

            while (offset < bytes.length) {
                const length = readUint32(bytes, offset);
                const type = ascii(bytes, offset + 4, 4);
                const dataStart = offset + 8;
                chunks.push({ type, data: bytes.slice(dataStart, dataStart + length) });
                offset = dataStart + length + 4;
            }

            const physData = new Uint8Array(9);
            writeUint32(physData, 0, pixelsPerMeter);
            writeUint32(physData, 4, pixelsPerMeter);
            physData[8] = 1;

            const outputChunks = [];
            let inserted = false;
            for (const chunk of chunks) {
                if (chunk.type === 'pHYs') {
                    if (!inserted) {
                        outputChunks.push({ type: 'pHYs', data: physData });
                        inserted = true;
                    }
                    continue;
                }
                outputChunks.push(chunk);
                if (chunk.type === 'IHDR' && !inserted) {
                    outputChunks.push({ type: 'pHYs', data: physData });
                    inserted = true;
                }
            }

            const encodedChunks = outputChunks.map(encodeChunk);
            const totalLength = signature.length + encodedChunks.reduce((sum, chunk) => sum + chunk.length, 0);
            const output = new Uint8Array(totalLength);
            output.set(signature, 0);
            let position = signature.length;
            for (const chunk of encodedChunks) {
                output.set(chunk, position);
                position += chunk.length;
            }
            return new Blob([output], { type: 'image/png' });
        }

        function encodeChunk(chunk) {
            const typeBytes = new TextEncoder().encode(chunk.type);
            const output = new Uint8Array(12 + chunk.data.length);
            writeUint32(output, 0, chunk.data.length);
            output.set(typeBytes, 4);
            output.set(chunk.data, 8);
            const crcInput = output.slice(4, 8 + chunk.data.length);
            writeUint32(output, 8 + chunk.data.length, crc32(crcInput));
            return output;
        }

        function readDpi(buffer, mimeType) {
            const bytes = new Uint8Array(buffer);
            if (mimeType === 'image/png') {
                return readPngDpi(bytes);
            }
            if (mimeType === 'image/jpeg') {
                return readJpegDpi(bytes);
            }
            return null;
        }

        function readPngDpi(bytes) {
            let offset = 8;
            while (offset < bytes.length) {
                const length = readUint32(bytes, offset);
                const type = ascii(bytes, offset + 4, 4);
                if (type === 'pHYs') {
                    const xPpm = readUint32(bytes, offset + 8);
                    const unit = bytes[offset + 16];
                    return unit === 1 ? xPpm / METERS_PER_INCH : null;
                }
                offset += 12 + length;
            }
            return null;
        }

        function readJpegDpi(bytes) {
            if (bytes[0] !== 0xff || bytes[1] !== 0xd8) {
                return null;
            }
            let offset = 2;
            while (offset < bytes.length) {
                if (bytes[offset] !== 0xff) {
                    break;
                }
                const marker = bytes[offset + 1];
                const length = (bytes[offset + 2] << 8) + bytes[offset + 3];
                if (marker === 0xe0 && ascii(bytes, offset + 4, 5) === 'JFIF\0') {
                    const unit = bytes[offset + 11];
                    const xDensity = (bytes[offset + 12] << 8) + bytes[offset + 13];
                    if (unit === 1) {
                        return xDensity;
                    }
                    if (unit === 2) {
                        return xDensity * 2.54;
                    }
                }
                if (marker === 0xe1 && ascii(bytes, offset + 4, 6) === 'Exif\0\0') {
                    const dpi = readExifDpi(bytes, offset + 10, length - 8);
                    if (dpi) {
                        return dpi;
                    }
                }
                offset += 2 + length;
            }
            return null;
        }

        function readExifDpi(bytes, tiffStart, length) {
            const little = ascii(bytes, tiffStart, 2) === 'II';
            const read16 = (position) => little ? bytes[position] + (bytes[position + 1] << 8) : (bytes[position] << 8) + bytes[position + 1];
            const read32 = (position) => little
                ? bytes[position] + (bytes[position + 1] << 8) + (bytes[position + 2] << 16) + (bytes[position + 3] << 24)
                : (bytes[position] << 24) + (bytes[position + 1] << 16) + (bytes[position + 2] << 8) + bytes[position + 3];
            const ifdOffset = read32(tiffStart + 4);
            const ifd = tiffStart + ifdOffset;
            if (ifd <= tiffStart || ifd >= tiffStart + length) {
                return null;
            }
            const count = read16(ifd);
            let xResolution = null;
            let unit = 2;
            for (let i = 0; i < count; i += 1) {
                const entry = ifd + 2 + i * 12;
                const tag = read16(entry);
                const type = read16(entry + 2);
                const valueOffset = read32(entry + 8);
                if (tag === 0x011a && type === 5) {
                    const rational = tiffStart + valueOffset;
                    const numerator = read32(rational);
                    const denominator = read32(rational + 4);
                    xResolution = denominator ? numerator / denominator : null;
                }
                if (tag === 0x0128) {
                    unit = read16(entry + 8);
                }
            }
            if (!xResolution) {
                return null;
            }
            return unit === 3 ? xResolution * 2.54 : xResolution;
        }

        function readUint32(bytes, offset) {
            return ((bytes[offset] << 24) | (bytes[offset + 1] << 16) | (bytes[offset + 2] << 8) | bytes[offset + 3]) >>> 0;
        }

        function writeUint32(bytes, offset, value) {
            bytes[offset] = (value >>> 24) & 255;
            bytes[offset + 1] = (value >>> 16) & 255;
            bytes[offset + 2] = (value >>> 8) & 255;
            bytes[offset + 3] = value & 255;
        }

        function ascii(bytes, offset, length) {
            return Array.from(bytes.slice(offset, offset + length), (byte) => String.fromCharCode(byte)).join('');
        }

        function crc32(bytes) {
            let crc = -1;
            for (let i = 0; i < bytes.length; i += 1) {
                crc = (crc >>> 8) ^ crcTable[(crc ^ bytes[i]) & 255];
            }
            return (crc ^ -1) >>> 0;
        }

        const crcTable = (() => {
            const table = new Uint32Array(256);
            for (let n = 0; n < 256; n += 1) {
                let c = n;
                for (let k = 0; k < 8; k += 1) {
                    c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
                }
                table[n] = c >>> 0;
            }
            return table;
        })();

        function makeOutputName(name) {
            return name.replace(/\.[^.]+$/, '').replace(/[^a-z0-9_-]+/gi, '-').replace(/-+/g, '-').replace(/^-|-$/g, '') + '-clean-300dpi.png';
        }

        function downloadBlob(blob, filename) {
            if (!blob) {
                return;
            }
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(link.href), 1000);
        }

        function sleep(ms) {
            return new Promise((resolve) => setTimeout(resolve, ms));
        }

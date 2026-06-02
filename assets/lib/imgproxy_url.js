// Kept in sync with SurvosImgproxyBundle::DEFAULT_PRESETS.
const DEFAULT_PRESETS = {
    tiny:    { width: 200,  height: 200,  resize: 'fit', quality: 70, format: 'webp' },
    thumb:   { width: 400,  height: 400,  resize: 'fit', quality: 80, format: 'webp' },
    observe: { width: 512,  height: 512,  resize: 'fit', quality: 80, format: 'webp' },
    display: { width: 600,  height: 400,  resize: 'fit', quality: 80, format: 'webp' },
    archive: { width: 3000, height: 3000, resize: 'fit', quality: 88, format: 'webp' },
};

export function imgproxyUrl(sourceUrl, options = {}) {
    const host = trimTrailingSlash(options.host || '');
    const source = String(sourceUrl || '').trim();

    if (!host || !source) {
        return source;
    }

    if (source.startsWith(`${host}/`)) {
        return source;
    }

    const presets = { ...DEFAULT_PRESETS, ...(options.presets || {}) };
    const presetName = options.preset || 'thumb';
    const preset = presets[presetName] || presets.thumb;
    const width = Number(options.width || preset.width || 400);
    const height = Number(options.height || preset.height || width);
    const resize = options.resize || preset.resize || 'fit';
    const format = options.format || preset.format || 'webp';
    const quality = Number(options.quality || preset.quality || 0);

    let processing = `rs:${resize}:${width}:${height}:0:0`;
    if (quality) {
        processing += `/q:${quality}`;
    }
    processing += `/f:${format}`;

    return `${host}/insecure/${processing}/plain/${encodePlainSource(source)}`;
}

function encodePlainSource(sourceUrl) {
    return String(sourceUrl)
        .replaceAll('&', '%26')
        .replaceAll('=', '%3D')
        .replaceAll('?', '%3F')
        .replaceAll('@', '%40');
}

function trimTrailingSlash(value) {
    return String(value).replace(/\/+$/, '');
}

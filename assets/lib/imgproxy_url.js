const DEFAULT_PRESETS = {
    ai: { width: 512, height: 512, resize: 'fit' },
    ai_thumbnail: { width: 512, height: 512, resize: 'fit' },
    ai_hires: { width: 2048, height: 2048, resize: 'fit' },
    thumb: { width: 300, height: 300, resize: 'fit' },
    small: { width: 192, height: 192, resize: 'fit' },
    medium: { width: 600, height: 400, resize: 'fit' },
    large: { width: 1600, height: 1600, resize: 'fit' },
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
    const width = Number(options.width || preset.width || 300);
    const height = Number(options.height || preset.height || width);
    const resize = options.resize || preset.resize || 'fit';
    const format = options.format || preset.format || 'jpg';
    const processing = `rs:${resize}:${width}:${height}:0`;

    return `${host}/insecure/${processing}/plain/${encodePlainSource(source)}@${format}`;
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

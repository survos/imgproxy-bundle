import { Controller } from '@hotwired/stimulus';
import { imgproxyUrl } from '../lib/imgproxy_url.js';

export default class extends Controller {
    static targets = ['image'];

    static values = {
        host: String,
        preset: { type: String, default: 'thumb' },
        format: { type: String, default: 'jpg' },
        presets: Object,
    };

    imageTargetConnected(element) {
        if (element.dataset.imgproxyDone === '1') {
            return;
        }

        const sourceUrl = this.sourceUrlFor(element);
        if (!sourceUrl) {
            return;
        }

        element.src = imgproxyUrl(sourceUrl, {
            host: element.dataset.imgproxyHost || this.hostValue,
            preset: element.dataset.imgproxyPreset || element.dataset.preset || this.presetValue,
            format: element.dataset.imgproxyFormat || this.formatValue,
            presets: this.hasPresetsValue ? this.presetsValue : undefined,
        });

        element.loading ||= 'lazy';
        element.dataset.imgproxyDone = '1';
    }

    imageTargetDisconnected(_element) {
    }

    sourceUrlFor(element) {
        return element.dataset.imgproxyUrl
            || element.dataset.url
            || element.dataset.src
            || element.getAttribute('src')
            || '';
    }
}


import { Controller } from '@hotwired/stimulus';
import { imgproxyUrl } from '../lib/imgproxy_url.js';

export default class extends Controller {
    static targets = ['image', 'status', 'url'];

    static values = {
        host: String,
        endpoint: String,
        preset: { type: String, default: 'thumb' },
        format: { type: String, default: 'jpg' },
        presets: Object,
    };

    connect() {
        this.element.dataset.imgproxyConnected = '1';
    }

    async imageTargetConnected(element) {
        if (element.dataset.imgproxyDone === '1') {
            return;
        }

        const sourceUrl = this.sourceUrlFor(element);
        if (!sourceUrl) {
            this.displayStatusForImage(element, 'Missing source URL');
            return;
        }

        let finalUrl;
        try {
            finalUrl = await this.finalUrlFor(element, sourceUrl);
        } catch (error) {
            this.displayUrlForImage(element, String(error));
            this.displayStatusForImage(element, String(error));
            return;
        }

        element.src = finalUrl;
        const link = element.closest('a');
        if (link) {
            link.href = finalUrl;
        }
        element.loading ||= 'lazy';
        element.dataset.imgproxyFinalUrl = finalUrl;
        element.dataset.imgproxyDone = '1';

        this.displayUrlForImage(element, finalUrl);
    }

    imageTargetDisconnected(_element) {
    }

    urlTargetConnected(element) {
        const item = element.closest('[data-imgproxy-item]');
        const image = item?.querySelector(`[data-${this.identifier}-target~="image"]`);
        const finalUrl = image?.dataset.imgproxyFinalUrl || image?.getAttribute('src') || '';

        if (finalUrl) {
            element.textContent = finalUrl;
        }
    }

    sourceUrlFor(element) {
        return element.dataset.imgproxyUrl
            || element.dataset.url
            || element.dataset.src
            || element.getAttribute('src')
            || '';
    }

    async finalUrlFor(element, sourceUrl) {
        const preset = element.dataset.imgproxyPreset || element.dataset.preset || this.presetValue;

        if (this.hasEndpointValue && this.endpointValue) {
            const endpoint = new URL(this.endpointValue, window.location.href);
            endpoint.searchParams.set('url', sourceUrl);
            endpoint.searchParams.set('preset', preset);

            const response = await fetch(endpoint.toString(), {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`imgproxy endpoint failed: ${response.status} ${response.statusText}`);
            }

            const data = await response.json();
            return data.url;
        }

        return imgproxyUrl(sourceUrl, {
            host: element.dataset.imgproxyHost || this.hostValue,
            preset,
            format: element.dataset.imgproxyFormat || this.formatValue,
            presets: this.hasPresetsValue ? this.presetsValue : undefined,
        });
    }

    displayUrlForImage(image, finalUrl) {
        const item = image.closest('[data-imgproxy-item]');
        if (!item) {
            return;
        }

        const target = this.urlTargets.find((urlTarget) => item.contains(urlTarget));
        if (target) {
            target.textContent = finalUrl;
        }
    }

    displayStatusForImage(image, message) {
        const item = image.closest('[data-imgproxy-item]');
        const targets = item
            ? this.statusTargets.filter((statusTarget) => item.contains(statusTarget))
            : this.statusTargets;

        targets.forEach((target) => {
            target.textContent = message;
        });
    }
}


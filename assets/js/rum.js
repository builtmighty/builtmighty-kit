/**
 * Real User Monitoring (RUM) for Built Mighty Kit
 *
 * Captures Core Web Vitals and sends them to the CRM.
 * Uses the web-vitals library pattern for accurate measurements.
 *
 * @package Built Mighty Kit
 * @since   4.5.0
 */
(function() {
    'use strict';

    // Configuration from localized script
    var config = window.kitRumConfig || {};
    if (!config.endpoint || !config.nonce) {
        return;
    }

    // Metrics storage
    var metrics = {
        lcp: null,
        fid: null,
        cls: null,
        ttfb: null,
        fcp: null,
        inp: null,
        url: window.location.pathname,
        device: getDeviceType(),
        connection: getConnectionType(),
        timestamp: Date.now()
    };

    /**
     * Detect device type
     */
    function getDeviceType() {
        var ua = navigator.userAgent;
        if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) {
            return 'tablet';
        }
        if (/Mobile|iP(hone|od)|Android|BlackBerry|IEMobile|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    /**
     * Get connection type if available
     */
    function getConnectionType() {
        var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (conn) {
            return conn.effectiveType || conn.type || 'unknown';
        }
        return 'unknown';
    }

    /**
     * Report a metric value
     */
    function reportMetric(name, value) {
        metrics[name] = Math.round(value);
    }

    /**
     * Send metrics to the server
     */
    function sendMetrics() {
        // Only send if we have at least one meaningful metric
        if (!metrics.lcp && !metrics.fid && !metrics.cls && !metrics.ttfb) {
            return;
        }

        var data = new FormData();
        data.append('action', 'kit_crm_rum_beacon');
        data.append('nonce', config.nonce);
        data.append('metrics', JSON.stringify(metrics));

        // Use sendBeacon for reliability
        if (navigator.sendBeacon) {
            var blob = new Blob([new URLSearchParams(data).toString()], {
                type: 'application/x-www-form-urlencoded'
            });
            navigator.sendBeacon(config.endpoint, blob);
        } else {
            // Fallback to fetch
            fetch(config.endpoint, {
                method: 'POST',
                body: data,
                keepalive: true
            }).catch(function() {
                // Silently fail
            });
        }
    }

    /**
     * Observe Largest Contentful Paint
     */
    function observeLCP() {
        if (!('PerformanceObserver' in window)) return;

        try {
            var observer = new PerformanceObserver(function(list) {
                var entries = list.getEntries();
                var lastEntry = entries[entries.length - 1];
                reportMetric('lcp', lastEntry.startTime);
            });
            observer.observe({ type: 'largest-contentful-paint', buffered: true });
        } catch (e) {
            // Not supported
        }
    }

    /**
     * Observe First Input Delay
     */
    function observeFID() {
        if (!('PerformanceObserver' in window)) return;

        try {
            var observer = new PerformanceObserver(function(list) {
                var entries = list.getEntries();
                var firstEntry = entries[0];
                reportMetric('fid', firstEntry.processingStart - firstEntry.startTime);
            });
            observer.observe({ type: 'first-input', buffered: true });
        } catch (e) {
            // Not supported
        }
    }

    /**
     * Observe Cumulative Layout Shift
     */
    function observeCLS() {
        if (!('PerformanceObserver' in window)) return;

        var clsValue = 0;
        var sessionValue = 0;
        var sessionEntries = [];

        try {
            var observer = new PerformanceObserver(function(list) {
                var entries = list.getEntries();
                entries.forEach(function(entry) {
                    if (!entry.hadRecentInput) {
                        var firstSessionEntry = sessionEntries[0];
                        var lastSessionEntry = sessionEntries[sessionEntries.length - 1];

                        if (sessionValue &&
                            entry.startTime - lastSessionEntry.startTime < 1000 &&
                            entry.startTime - firstSessionEntry.startTime < 5000) {
                            sessionValue += entry.value;
                            sessionEntries.push(entry);
                        } else {
                            sessionValue = entry.value;
                            sessionEntries = [entry];
                        }

                        if (sessionValue > clsValue) {
                            clsValue = sessionValue;
                            reportMetric('cls', clsValue * 1000); // Store as integer (multiply by 1000)
                        }
                    }
                });
            });
            observer.observe({ type: 'layout-shift', buffered: true });
        } catch (e) {
            // Not supported
        }
    }

    /**
     * Observe Interaction to Next Paint (INP)
     */
    function observeINP() {
        if (!('PerformanceObserver' in window)) return;

        var interactions = [];

        try {
            var observer = new PerformanceObserver(function(list) {
                var entries = list.getEntries();
                entries.forEach(function(entry) {
                    if (entry.interactionId) {
                        interactions.push(entry.duration);
                        // Report the 98th percentile
                        interactions.sort(function(a, b) { return a - b; });
                        var p98Index = Math.floor(interactions.length * 0.98);
                        reportMetric('inp', interactions[p98Index] || interactions[interactions.length - 1]);
                    }
                });
            });
            observer.observe({ type: 'event', buffered: true, durationThreshold: 40 });
        } catch (e) {
            // Not supported
        }
    }

    /**
     * Get navigation timing metrics
     */
    function getNavigationTiming() {
        if (!('performance' in window) || !performance.getEntriesByType) return;

        var entries = performance.getEntriesByType('navigation');
        if (entries.length === 0) return;

        var nav = entries[0];

        // Time to First Byte
        if (nav.responseStart > 0) {
            reportMetric('ttfb', nav.responseStart);
        }

        // First Contentful Paint
        var paintEntries = performance.getEntriesByType('paint');
        paintEntries.forEach(function(entry) {
            if (entry.name === 'first-contentful-paint') {
                reportMetric('fcp', entry.startTime);
            }
        });
    }

    /**
     * Initialize observers
     */
    function init() {
        // Start observers
        observeLCP();
        observeFID();
        observeCLS();
        observeINP();

        // Get navigation timing after load
        if (document.readyState === 'complete') {
            getNavigationTiming();
        } else {
            window.addEventListener('load', function() {
                // Delay to ensure metrics are available
                setTimeout(getNavigationTiming, 0);
            });
        }

        // Send metrics on page hide (works better than unload)
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                sendMetrics();
            }
        });

        // Fallback: send after 30 seconds if page is still visible
        setTimeout(function() {
            if (document.visibilityState !== 'hidden') {
                sendMetrics();
            }
        }, 30000);
    }

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

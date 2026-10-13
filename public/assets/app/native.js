/*
 | The phone's own features, for the app's pages.
 |
 | NativePHP runs this Laravel app inside an Android WebView. Pages call native functions with
 | a POST to /_native/api/call, and native results that arrive later (a file picked, a "Save as"
 | finished) come back as a `native-event` on the document. This wraps both in promises:
 |
 |   const file = await ZentroNative.pickFile(['image/*']);          // {path, name} or null
 |   const name = await ZentroNative.saveFile(path, 'x.pdf', mime);   // saved name or null
 |   await ZentroNative.shareFile(path, 'caption');
 |   await ZentroNative.whatsappFile(path, '919688527308', 'caption');
 |
 | Every POST carries the CSRF token in the body as `_token`, not only in the header. NativePHP's
 | Android layer overwrites X-CSRF-TOKEN with a token it remembered from an earlier page, which
 | goes stale whenever the session's token changes (the PIN lock signs out and in). Laravel reads
 | `_token` from the body before the header, so the body copy is what counts.
 */
(function () {
    'use strict';

    var PICKED = 'Smronju\\NativephpDocumentPicker\\Events\\DocumentPicked';
    var SAVED = 'Smronju\\NativephpDocumentPicker\\Events\\DocumentSaved';

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    /** A native bridge function. Resolves with its result, rejects with its error. */
    function call(method, params) {
        return fetch('/_native/api/call', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ method: method, params: params || {}, _token: csrf() }),
        })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                if (result.status === 'error') { throw new Error(result.message || 'The phone could not do that.'); }
                var data = result.data && result.data.data !== undefined ? result.data.data : result.data;
                if (data && data.error) { throw new Error(data.error); }
                return data;
            });
    }

    /** The next native-event of this name; its payload. Rejects after `timeoutMs`. */
    function next(eventName, timeoutMs) {
        return new Promise(function (resolve, reject) {
            var timer = setTimeout(function () {
                document.removeEventListener('native-event', listener);
                reject(new Error('timeout'));
            }, timeoutMs);

            function listener(e) {
                if (!e.detail || e.detail.event !== eventName) { return; }
                clearTimeout(timer);
                document.removeEventListener('native-event', listener);
                resolve(e.detail.payload || {});
            }

            document.addEventListener('native-event', listener);
        });
    }

    /** JSON POST to the app's own server side, with CSRF. Rejects with the error message. */
    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(Object.assign({ _token: csrf() }, data || {})),
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (body) {
                if (!r.ok || body.error) { throw new Error(body.error || body.message || 'Something went wrong.'); }
                return body;
            });
        });
    }

    var MINUTE = 60 * 1000;

    window.ZentroNative = {
        call: call,
        post: post,

        /** The system file picker. {path, name}, or null when cancelled. */
        pickFile: function (mimeTypes) {
            var result = next(PICKED, 10 * MINUTE);
            return call('DocumentPicker.Pick', { mime_types: mimeTypes || ['*/*'] })
                .then(function () { return result; })
                .then(function (p) { return p.path ? p : null; });
        },

        /** Android's "Save as" screen. The saved file's name, or null when cancelled. */
        saveFile: function (path, name, mime) {
            var result = next(SAVED, 10 * MINUTE);
            return call('DocumentPicker.Save', { source_path: path, suggested_name: name, mime_type: mime || '*/*' })
                .then(function () { return result; })
                .then(function (p) { return p.name || null; });
        },

        /** The share sheet with a file attached. */
        shareFile: function (path, text, title) {
            return call('Share.File', { title: title || 'Share', message: text || '', filePath: path });
        },

        /** WhatsApp, opened on this number's chat with the file attached. */
        whatsappFile: function (path, phone, text, app) {
            return call('ZentroWhatsApp.SendFile', { filePath: path, phone: phone || '', text: text || '', app: app || 'business' });
        },

        /**
         * A picked image as a JPEG data URL, drawn through a canvas onto white and scaled to fit
         * maxWidth × maxHeight. Any format the phone's browser can show (JPEG, PNG, HEIC on most
         * phones, WebP) comes out as a JPEG of the size it will be printed at.
         *
         * JPEG because the phone's PHP has no GD: the server cannot resize or convert a picture,
         * and the PDF renderer can only embed a JPEG without GD (a PNG needs it).
         */
        imageAsJpeg: function (path, maxWidth, maxHeight) {
            maxWidth = maxWidth || 1200;
            maxHeight = maxHeight || maxWidth;
            return new Promise(function (resolve, reject) {
                var img = new Image();
                img.onload = function () {
                    var scale = Math.min(1, maxWidth / img.naturalWidth, maxHeight / img.naturalHeight);
                    var canvas = document.createElement('canvas');
                    canvas.width = Math.max(1, Math.round(img.naturalWidth * scale));
                    canvas.height = Math.max(1, Math.round(img.naturalHeight * scale));
                    var ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    resolve(canvas.toDataURL('image/jpeg', 0.92));
                };
                img.onerror = function () { reject(new Error('That file is not a picture this phone can open.')); };
                img.src = '/device-file?path=' + encodeURIComponent(path) + '&t=' + Date.now();
            });
        },
    };
})();

/* =====================================================
   FILES — keeps uploaded documents in the browser (IndexedDB)
   so the student portal can upload and the admin portal can open
   the same file. Both portals share it because they share an origin.
   Replace with a PHP upload + download endpoint later:
     GTFiles.put(key, file)  -> POST /api/uploads
     GTFiles.open(key)       -> GET  /api/uploads/:id
   key = "<studentId>|<requirementId>"
===================================================== */

(function () {
    "use strict";

    var DB_NAME = "grantrackFiles", STORE = "files";

    function db() {
        return new Promise(function (resolve, reject) {
            var req = indexedDB.open(DB_NAME, 1);
            req.onupgradeneeded = function () { req.result.createObjectStore(STORE); };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { reject(req.error); };
        });
    }

    function run(mode, fn) {
        return db().then(function (d) {
            return new Promise(function (resolve, reject) {
                var tx = d.transaction(STORE, mode);
                var result = fn(tx.objectStore(STORE));
                tx.oncomplete = function () { resolve(result && result.result); d.close(); };
                tx.onerror = function () { reject(tx.error); };
            });
        });
    }

    window.GTFiles = {
        put: function (key, file) {
            return run("readwrite", function (s) {
                return s.put({ blob: file, name: file.name, type: file.type, size: file.size }, key);
            });
        },
        get: function (key) {
            return run("readonly", function (s) { return s.get(key); });
        },
        // Saves the stored file to the computer with its original name.
        download: function (key) {
            return this.get(key).then(function (rec) {
                if (!rec) return false;
                var url = URL.createObjectURL(rec.blob);
                var a = document.createElement("a");
                a.href = url;
                a.download = rec.name;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(function () { URL.revokeObjectURL(url); }, 2000);
                return true;
            });
        },
        // Opens the stored file in a new tab. Resolves false if there is none.
        open: function (key) {
            var tab = window.open("", "_blank");
            return this.get(key).then(function (rec) {
                if (!rec) { if (tab) tab.close(); return false; }
                var url = URL.createObjectURL(rec.blob);
                if (tab) tab.location.href = url;
                else window.location.href = url;
                return true;
            });
        }
    };
})();

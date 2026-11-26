/* Suchmaschine – Weiterleitung bei Bangs mit Herkunft (nur die Adresse der Suchmaschine, siehe smExternalRedirect) */
(function () {
    'use strict';
    var s = document.currentScript, to = s && s.getAttribute('data-go');
    if (to && /^https?:\/\//i.test(to)) location.replace(to);
})();

/**
 * SwiftBoard — pagination « Charger plus » par curseur.
 *
 * POURQUOI CE FICHIER EST EXTERNE
 * -------------------------------
 * Ce comportement etait imprime en <script> inline dans wp_footer. Le theme
 * sert une CSP `script-src 'self'` en ENFORCE : un bloc inline non hache est
 * refuse par le navigateur, silencieusement — le bouton restait inerte sans la
 * moindre erreur PHP. Un fichier externe est couvert par 'self'.
 *
 * POURQUOI LES PARAMETRES VIENNENT D'ATTRIBUTS data-*
 * ---------------------------------------------------
 * La version precedente deduisait le forum courant de window.location, puis
 * ne l'ajoutait JAMAIS a la requete : l'endpoint renvoyait les sujets de tous
 * les forums, y compris sur la page d'un forum precis. Le serveur connait le
 * forum courant — il le transmet donc directement, comme pour le bloc de vote.
 *
 * @package SwiftBoard
 * @since 5.1.1
 */
(function () {
    'use strict';

    /**
     * Construit l'URL de l'endpoint a partir des attributs du bouton.
     *
     * @param {HTMLElement} btn    Bouton « Charger plus ».
     * @param {number}      cursor Dernier identifiant charge.
     * @return {string} URL complete.
     */
    function construireUrl(btn, cursor) {
        const base = btn.getAttribute('data-rest-url');
        if (!base) {
            return '';
        }

        // rest_url() peut deja porter une query string (permaliens simples :
        // /?rest_route=/...). On choisit le bon separateur.
        let url = base + (base.indexOf('?') === -1 ? '?' : '&') + 'cursor=' + encodeURIComponent(cursor);

        const forumId = parseInt(btn.getAttribute('data-forum-id'), 10) || 0;
        if (forumId > 0) {
            url += '&forum_id=' + encodeURIComponent(forumId);
        }

        url += '&sort=' + encodeURIComponent(btn.getAttribute('data-sort') || 'hot');
        url += '&period=' + encodeURIComponent(btn.getAttribute('data-period') || '7d');

        return url;
    }

    /**
     * Cree la carte d'un sujet.
     *
     * Le contenu passe par textContent et non innerHTML : les titres et
     * extraits viennent de la base et ne doivent jamais etre interpretes.
     *
     * @param {Object} t Sujet renvoye par l'endpoint.
     * @return {HTMLElement}
     */
    function creerCarte(t) {
        const article = document.createElement('article');
        article.className = 'sb-post-card sb-home-card';

        const votes = document.createElement('div');
        votes.className = 'sb-post-votes';

        const haut = document.createElement('button');
        haut.className = 'sb-vote-btn up';
        haut.setAttribute('data-post-id', t.id);
        haut.setAttribute('data-vote', 'up');
        haut.setAttribute('aria-label', 'Upvoter');
        haut.textContent = '▲';

        const score = document.createElement('span');
        score.className = 'sb-vote-count';
        score.textContent = t.votes;

        const bas = document.createElement('button');
        bas.className = 'sb-vote-btn down';
        bas.setAttribute('data-post-id', t.id);
        bas.setAttribute('data-vote', 'down');
        bas.setAttribute('aria-label', 'Downvoter');
        bas.textContent = '▼';

        votes.appendChild(haut);
        votes.appendChild(score);
        votes.appendChild(bas);

        const contenu = document.createElement('div');
        contenu.className = 'sb-post-content';

        const meta = document.createElement('div');
        meta.className = 'sb-post-meta-top';

        const forum = document.createElement('a');
        forum.className = 'sb-forum-pill';
        forum.href = t.forum_url || '#';
        forum.textContent = t.forum_name || '';

        const auteur = document.createElement('span');
        auteur.className = 'sb-post-author';
        auteur.textContent = t.author_name || '';

        const date = document.createElement('span');
        date.className = 'sb-post-time';
        date.textContent = t.date || '';

        meta.appendChild(forum);
        meta.appendChild(auteur);
        meta.appendChild(date);

        const titre = document.createElement('h2');
        titre.className = 'sb-post-title';
        const lien = document.createElement('a');
        lien.href = t.url || '#';
        lien.textContent = t.title || '';
        titre.appendChild(lien);

        const extrait = document.createElement('p');
        extrait.className = 'sb-post-body';
        extrait.textContent = t.excerpt || '';

        contenu.appendChild(meta);
        contenu.appendChild(titre);
        contenu.appendChild(extrait);

        article.appendChild(votes);
        article.appendChild(contenu);

        return article;
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.sb-load-more-btn');
        if (!btn) {
            return;
        }
        e.preventDefault();

        if (btn.classList.contains('is-loading')) {
            return;
        }

        const cursor = parseInt(btn.getAttribute('data-cursor'), 10) || 0;
        if (!cursor) {
            return;
        }

        const url = construireUrl(btn, cursor);
        if (!url) {
            return;
        }

        btn.classList.add('is-loading');
        const libelle = btn.textContent;
        btn.textContent = 'Chargement…';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                return r.ok ? r.json() : null;
            })
            .then(function (data) {
                if (!data || !data.topics || !data.topics.length) {
                    btn.textContent = 'Plus aucun sujet';
                    btn.classList.remove('is-loading');
                    btn.classList.add('is-empty');
                    return;
                }

                // « .bbp-topics-list » est le conteneur reellement rendu par
                // bbpress/content-single-forum.php ; il manquait a cette liste,
                // si bien que la reponse arrivait (HTTP 200) mais qu'aucun sujet
                // n'etait ajoute au DOM — le bouton semblait sans effet.
                const conteneur = document.querySelector(
                    '.bbp-topics-list, .bbp-topics, .sb-topics-list, .sb-feed');
                if (!conteneur) {
                    // Repli sans JavaScript de rendu : on suit le lien, qui
                    // porte deja le curseur.
                    window.location.href = btn.href;
                    return;
                }

                data.topics.forEach(function (t) {
                    conteneur.appendChild(creerCarte(t));
                });

                if (data.has_more && data.next_cursor) {
                    btn.setAttribute('data-cursor', data.next_cursor);
                    btn.textContent = libelle;
                } else {
                    btn.textContent = 'Plus aucun sujet';
                    btn.classList.add('is-empty');
                }
                btn.classList.remove('is-loading');
            })
            .catch(function () {
                btn.textContent = 'Erreur — réessayer';
                btn.classList.remove('is-loading');
            });
    });
})();


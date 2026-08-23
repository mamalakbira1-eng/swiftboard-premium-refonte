/* SwiftBoard best-answer actions. Extracted from inc/best-answer.php. */
(function(){
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.sb-action-solve');
        if (!btn) return;
        e.preventDefault();
        var topicId = btn.getAttribute('data-topic-id');
        var replyId = btn.getAttribute('data-reply-id');
        var nonce = btn.getAttribute('data-nonce');
        if (!topicId || !replyId) return;
        btn.disabled = true;
        var isActive = btn.classList.contains('active');
        var targetReplyId = isActive ? 0 : parseInt(replyId,10);
        fetch('/wp-json/swiftboard/v1/topic/' + topicId + '/solve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({reply_id: targetReplyId})
        }).then(function(r){ return r.json(); }).then(function(data){
            if (data && data.success) {
                location.reload();
            } else {
                btn.disabled = false;
                var orig = btn.textContent;
                btn.textContent = data.message || 'Erreur';
                btn.style.opacity = '0.7';
                setTimeout(function(){ btn.textContent = orig; btn.style.opacity = ''; }, 2500);
            }
        }).catch(function(){
            btn.disabled = false;
        });
    });
})();

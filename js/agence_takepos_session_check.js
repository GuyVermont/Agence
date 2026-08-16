(function () {
    'use strict';
    if ((window.location.pathname || '').indexOf('/takepos/') === -1) return;

    function banner(message, ok) {
        var old = document.getElementById('agence-takepos-session-banner');
        if (old) old.remove();
        var node = document.createElement('div');
        node.id = 'agence-takepos-session-banner';
        node.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:100000;padding:12px;text-align:center;color:#fff;font-weight:600;background:' + (ok ? '#15803d' : '#b91c1c');
        node.textContent = message;
        document.body.appendChild(node);
        document.body.style.paddingTop = (node.offsetHeight + 4) + 'px';
    }

    function disableSale() {
        ['.poscolorblue', '.validation', '#payment', '.savebutton', 'button[onclick*=Paiement]', 'button[onclick*=Validation]'].forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (button) {
				if (button.disabled && button.dataset.agenceDisabled !== '1') return;
				button.dataset.agenceDisabled = '1';
                button.disabled = true;
                button.style.opacity = '0.4';
                button.style.pointerEvents = 'none';
                button.title = 'Aucune session Agence ouverte';
            });
        });
    }

	function enableSale() {
		document.querySelectorAll('[data-agence-disabled="1"]').forEach(function (button) {
			button.disabled = false;
			button.style.opacity = '';
			button.style.pointerEvents = '';
			button.title = '';
			delete button.dataset.agenceDisabled;
		});
	}

    function check() {
        var marker = '/takepos/';
        var base = window.location.pathname.substring(0, window.location.pathname.indexOf(marker));
        fetch(base + '/custom/agence/ajax/check_takepos_session.php', {credentials: 'same-origin'})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.ok) {
					enableSale();
                    banner('Session Agence active : ' + data.session_ref + ' — ' + data.caisse, true);
                    setTimeout(function () {
                        var node = document.getElementById('agence-takepos-session-banner');
                        if (node) node.remove();
                        document.body.style.paddingTop = '';
                    }, 3500);
                } else {
                    banner('VENTE BLOQUÉE : ' + (data.warning || 'aucune session Agence ouverte'), false);
                    disableSale();
                    setTimeout(check, 30000);
                }
            })
            .catch(function () {
                banner('VENTE BLOQUÉE : contrôle de session Agence indisponible', false);
                disableSale();
            });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', check);
    else check();
}());

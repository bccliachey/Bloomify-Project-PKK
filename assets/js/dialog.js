/* Bloomify - shared confirmation dialog and auto-dismissing notifications */
(function () {
    'use strict';

    var overlay = null;
    var titleElement = null;
    var messageElement = null;
    var confirmButton = null;
    var cancelButton = null;
    var closeButton = null;
    var fieldWrapper = null;
    var fieldLabel = null;
    var fieldInput = null;
    var fieldError = null;
    var mode = 'confirm';
    var resolver = null;
    var lastFocused = null;

    function ensureDialog() {
        if (overlay) {
            return;
        }

        overlay = document.createElement('div');
        overlay.className = 'bloomify-dialog';
        overlay.setAttribute('aria-hidden', 'true');

        var panel = document.createElement('div');
        panel.className = 'bloomify-dialog-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-labelledby', 'bloomifyDialogTitle');
        panel.setAttribute('aria-describedby', 'bloomifyDialogMessage');

        closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'bloomify-dialog-close';
        closeButton.setAttribute('aria-label', 'Close dialog');
        closeButton.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';

        titleElement = document.createElement('h2');
        titleElement.id = 'bloomifyDialogTitle';
        titleElement.textContent = 'Please confirm';

        messageElement = document.createElement('p');
        messageElement.id = 'bloomifyDialogMessage';

        fieldWrapper = document.createElement('div');
        fieldWrapper.className = 'bloomify-dialog-field hidden';

        fieldLabel = document.createElement('label');
        fieldLabel.setAttribute('for', 'bloomifyDialogField');

        fieldInput = document.createElement('textarea');
        fieldInput.id = 'bloomifyDialogField';
        fieldInput.rows = 3;

        fieldError = document.createElement('span');
        fieldError.className = 'bloomify-dialog-field-error hidden';

        fieldWrapper.appendChild(fieldLabel);
        fieldWrapper.appendChild(fieldInput);
        fieldWrapper.appendChild(fieldError);

        var actions = document.createElement('div');
        actions.className = 'bloomify-dialog-actions';

        cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'btn btn-soft';
        cancelButton.textContent = 'Cancel';

        confirmButton = document.createElement('button');
        confirmButton.type = 'button';
        confirmButton.className = 'btn btn-primary';
        confirmButton.textContent = 'Confirm';

        actions.appendChild(cancelButton);
        actions.appendChild(confirmButton);
        panel.appendChild(closeButton);
        panel.appendChild(titleElement);
        panel.appendChild(messageElement);
        panel.appendChild(fieldWrapper);
        panel.appendChild(actions);
        overlay.appendChild(panel);
        document.body.appendChild(overlay);

        closeButton.addEventListener('click', function () {
            resolve(false);
        });

        cancelButton.addEventListener('click', function () {
            resolve(false);
        });

        confirmButton.addEventListener('click', function () {
            if (mode === 'prompt') {
                var value = fieldInput.value.trim();

                if (value === '') {
                    fieldError.classList.remove('hidden');
                    fieldInput.focus();
                    return;
                }

                resolve(value);
                return;
            }

            resolve(true);
        });

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                resolve(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (!overlay.classList.contains('show')) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                resolve(false);
            }

            if (event.key === 'Tab') {
                var focusable = panel.querySelectorAll('button:not([disabled])');
                if (!focusable.length) {
                    return;
                }

                var first = focusable[0];
                var last = focusable[focusable.length - 1];

                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });
    }

    function resolve(result) {
        if (!overlay) {
            return;
        }

        overlay.classList.remove('show');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('dialog-open');

        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }

        if (resolver) {
            var callback = resolver;
            resolver = null;
            callback(result);
        }
    }

    function confirmAction(message, options) {
        ensureDialog();

        options = options || {};
        mode = 'confirm';
        lastFocused = document.activeElement;
        fieldWrapper.classList.add('hidden');
        fieldError.classList.add('hidden');
        titleElement.textContent = options.title || 'Please confirm';
        messageElement.textContent = message || 'Are you sure?';
        confirmButton.textContent = options.confirmLabel || 'Confirm';
        confirmButton.classList.toggle('btn-danger', Boolean(options.danger));
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('dialog-open');

        if (options.danger) {
            confirmButton.classList.add('btn-danger');
        }

        window.setTimeout(function () {
            confirmButton.focus();
        }, 20);

        return new Promise(function (resolvePromise) {
            resolver = resolvePromise;
        });
    }

    function promptAction(message, options) {
        ensureDialog();

        options = options || {};
        mode = 'prompt';
        lastFocused = document.activeElement;
        titleElement.textContent = options.title || 'Please provide a reason';
        messageElement.textContent = message || '';
        confirmButton.textContent = options.confirmLabel || 'Confirm';
        confirmButton.classList.toggle('btn-danger', Boolean(options.danger));

        fieldLabel.textContent = options.label || 'Reason';
        fieldInput.value = options.value || '';
        fieldInput.placeholder = options.placeholder || '';
        fieldInput.maxLength = options.maxLength || 255;
        fieldError.textContent = options.errorMessage || 'Please enter a reason.';
        fieldError.classList.add('hidden');
        fieldWrapper.classList.remove('hidden');

        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('dialog-open');

        window.setTimeout(function () {
            fieldInput.focus();
        }, 20);

        return new Promise(function (resolvePromise) {
            resolver = resolvePromise;
        });
    }

    function confirmData(element) {
        return confirmAction(element.getAttribute('data-confirm'), {
            title: element.getAttribute('data-confirm-title') || 'Please confirm',
            confirmLabel: element.getAttribute('data-confirm-label') || 'Confirm',
            danger: element.hasAttribute('data-confirm-danger') || element.getAttribute('data-confirm-danger') === 'true'
        });
    }

    window.BloomifyDialog = {
        confirm: confirmAction,
        prompt: promptAction
    };

    function bindConfirmations() {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.confirmBypass === '1') {
                    delete form.dataset.confirmBypass;
                    return;
                }

                var validationEvent = new CustomEvent('bloomify:beforeconfirm', { cancelable: true });
                form.dispatchEvent(validationEvent);

                if (validationEvent.defaultPrevented) {
                    event.preventDefault();
                    return;
                }

                var submitter = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');

                event.preventDefault();
                confirmData(form).then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    form.dataset.confirmBypass = '1';

                    if (typeof form.requestSubmit === 'function') {
                        if (submitter) {
                            form.requestSubmit(submitter);
                        } else {
                            form.requestSubmit();
                        }
                    } else {
                        form.submit();
                    }
                });
            });
        });

        document.querySelectorAll('[data-confirm]:not(form)').forEach(function (element) {
            element.addEventListener('click', function (event) {
                if (element.dataset.confirmBypass === '1') {
                    delete element.dataset.confirmBypass;
                    return;
                }

                event.preventDefault();
                confirmData(element).then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    element.dataset.confirmBypass = '1';

                    if (element.tagName === 'A' && element.href) {
                        window.location.href = element.href;
                    } else if (typeof element.click === 'function') {
                        element.click();
                    }
                });
            });
        });
    }

    function bindNotifications() {
        document.querySelectorAll('.flash').forEach(function (flash) {
            if (flash.hasAttribute('data-flash-persistent')) {
                return;
            }

            window.setTimeout(function () {
                flash.classList.add('flash-leaving');
                window.setTimeout(function () {
                    flash.remove();
                }, 220);
            }, 4000);
        });
    }

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    onReady(function () {
        bindConfirmations();
        bindNotifications();
    });
})();

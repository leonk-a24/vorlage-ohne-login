function togglePassword(button) {
    const input = button.previousElementSibling;
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.textContent = show ? 'Verbergen' : 'Anzeigen';
}

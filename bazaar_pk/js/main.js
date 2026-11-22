// main.js — shared JavaScript for all pages

// ── Password show/hide toggle ──
// Called by the eye icon on signup and login
function togglePass() {
    const field = document.getElementById('passField');
    const eye   = document.querySelector('.form-eye');

    if (field.type === 'password') {
        field.type = 'text';
        // Turn eye teal to show it's active
        eye.style.color = '#2DD4BF';
    } else {
        field.type = 'password';
        eye.style.color = '#64748B';
    }
}

// ── Role selector (signup page only) ──
// Updates the hidden input and highlights the selected button
function selectRole(role) {
    document.getElementById('roleInput').value = role;

    // Remove selected class from both buttons first
    document.getElementById('btnCustomer').classList.remove('selected');
    document.getElementById('btnVendor').classList.remove('selected');

    // Add selected class to whichever was clicked
    if (role === 'customer') {
        document.getElementById('btnCustomer').classList.add('selected');
    } else {
        document.getElementById('btnVendor').classList.add('selected');
    }
}
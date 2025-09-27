document.addEventListener("DOMContentLoaded", function() {
    // This function will look for a button with the ID 'google-signin-btn' on any page it's included on.
    const googleSignInButton = document.getElementById('google-signin-btn');

    if (googleSignInButton) {
        googleSignInButton.addEventListener('click', () => {
            const provider = new firebase.auth.GoogleAuthProvider();

            firebase.auth().signInWithPopup(provider)
                .then((result) => {
                    const user = result.user;
                    // After a successful sign-in, get the ID token
                    user.getIdToken().then((idToken) => {
                        // Send the token to the correct path in the auth directory
                        postToServer('/medsync/auth/google_auth_process.php', { id_token: idToken });
                    });
                }).catch((error) => {
                    console.error("Google Sign-In Error:", error);
                    alert(`Google Sign-In failed: ${error.message}`);
                });
        });
    }
});

/**
 * Creates and submits a form to post data to the server.
 * @param {string} url The URL to post to.
 * @param {object} data The data to post as key-value pairs.
 */
function postToServer(url, data) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.style.display = 'none'; // Hide the form

    for (const key in data) {
        if (data.hasOwnProperty(key)) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = key;
            hiddenField.value = data[key];
            form.appendChild(hiddenField);
        }
    }
    document.body.appendChild(form);
    form.submit();
}

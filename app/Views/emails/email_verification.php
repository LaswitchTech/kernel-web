<p>Hello, <?= htmlspecialchars($userName) ?></p>

<p>Thank you for signing up! Please verify your email address by clicking the link below:</p>

<p style="margin: 24px 0;">
    <a href="<?= htmlspecialchars($verifyUrl) ?>"
       style="background-color: #0d6efd; color: #ffffff; padding: 10px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
        Verify your email
    </a>
</p>

<p>This link will expire in 24 hours. If you did not create an account, you can safely ignore this email.</p>

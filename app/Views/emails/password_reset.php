<p>Hello, <?= htmlspecialchars($userName) ?></p>

<p>We received a request to reset your password. Click the link below to continue:</p>

<p style="margin: 24px 0;">
    <a href="<?= htmlspecialchars($resetUrl) ?>"
       style="background-color: #0d6efd; color: #ffffff; padding: 10px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
        Reset your password
    </a>
</p>

<p>This link will expire in <?= $expiresMinutes ?> minutes. If you did not request a password reset, you can safely ignore this email.</p>

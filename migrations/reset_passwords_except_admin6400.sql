
-- Password awal semua akun selain admin6400: 123 (12 November 2026).
UPDATE users SET password_hash='$2y$12$87mGp7x3SINe/GRk6VsUteNlL/E331T1YldI3fOLcVUiw64F9ffdO' WHERE username <> 'admin6400';

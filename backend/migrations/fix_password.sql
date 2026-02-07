-- Ejecutar en phpMyAdmin para corregir el hash de password
-- Password: Admin123!
UPDATE usuarios SET password_hash = '$2y$12$P1D9aKHijDFG6gdUj5DF8uuYH34P1mc2V4dXeiAk4T0przqKTJ4OO';

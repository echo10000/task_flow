USE taskflow;

UPDATE users
SET
    password = '$2y$10$liOxiOdI7FLP1ya6iHXK7exrDLKn6F6TOEwPQt01ZLhx0e5Qm6any',
    role = 'admin',
    active = 1
WHERE username = 'admin';

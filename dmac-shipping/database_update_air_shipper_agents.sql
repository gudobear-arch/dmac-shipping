-- =========================================================
-- DMAC UPDATE: Air Shipper Agents
-- Purpose: Air Details should use Shipper Agent role just like Land uses Driver role.
-- =========================================================

-- 1. Add Shipper Agent role if missing
INSERT INTO roles (role)
SELECT 'Shipper Agent'
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE LOWER(role) = 'shipper agent'
);

-- 2. Add sample shipper agents as employee records if missing
-- Password hash is a placeholder hashed password for test accounts.
INSERT INTO employee (
    dept_ID,
    emp_firstname,
    emp_lastname,
    emp_contact,
    emp_email,
    emp_password,
    registered_since,
    account_status,
    is_super_admin
)
SELECT
    COALESCE((SELECT dept_ID FROM department ORDER BY dept_ID LIMIT 1), 1),
    'AeroSwift',
    'Global Cargo',
    '09000000001',
    'aeroswift@dmac.test',
    '$2y$10$wH6Q6wYFv7K1MzW7Zf6xQOyUCF8WpH9S1AvlL6qD9qMfUo7v5nX7e',
    NOW(),
    'approved',
    0
WHERE NOT EXISTS (
    SELECT 1 FROM employee WHERE emp_email = 'aeroswift@dmac.test'
);

INSERT INTO employee (
    dept_ID,
    emp_firstname,
    emp_lastname,
    emp_contact,
    emp_email,
    emp_password,
    registered_since,
    account_status,
    is_super_admin
)
SELECT
    COALESCE((SELECT dept_ID FROM department ORDER BY dept_ID LIMIT 1), 1),
    'StratoFreight',
    'Logistics',
    '09000000002',
    'stratofreight@dmac.test',
    '$2y$10$wH6Q6wYFv7K1MzW7Zf6xQOyUCF8WpH9S1AvlL6qD9qMfUo7v5nX7e',
    NOW(),
    'approved',
    0
WHERE NOT EXISTS (
    SELECT 1 FROM employee WHERE emp_email = 'stratofreight@dmac.test'
);

INSERT INTO employee (
    dept_ID,
    emp_firstname,
    emp_lastname,
    emp_contact,
    emp_email,
    emp_password,
    registered_since,
    account_status,
    is_super_admin
)
SELECT
    COALESCE((SELECT dept_ID FROM department ORDER BY dept_ID LIMIT 1), 1),
    'JetStream',
    'Courier Corp',
    '09000000003',
    'jetstream@dmac.test',
    '$2y$10$wH6Q6wYFv7K1MzW7Zf6xQOyUCF8WpH9S1AvlL6qD9qMfUo7v5nX7e',
    NOW(),
    'approved',
    0
WHERE NOT EXISTS (
    SELECT 1 FROM employee WHERE emp_email = 'jetstream@dmac.test'
);

-- 3. Assign Shipper Agent role to the sample air shipper agents
INSERT INTO emprole (emp_ID, role_ID, is_coordinator)
SELECT e.emp_ID, r.role_ID, 0
FROM employee e
JOIN roles r ON LOWER(r.role) = 'shipper agent'
WHERE e.emp_email IN (
    'aeroswift@dmac.test',
    'stratofreight@dmac.test',
    'jetstream@dmac.test'
)
AND NOT EXISTS (
    SELECT 1
    FROM emprole er
    WHERE er.emp_ID = e.emp_ID
      AND er.role_ID = r.role_ID
);

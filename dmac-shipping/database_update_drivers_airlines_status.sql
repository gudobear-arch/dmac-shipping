-- =========================================================
-- DMAC UPDATE: Drivers + Airline Options + Default Approved Status
-- =========================================================
-- This update makes Land driver dropdown use Driver role employees,
-- Air airline field use fixed options in PHP, and approved bookings
-- move to FOR PICK-UP instead of PROCESSING.

-- 1. Make sure Driver role exists.
INSERT INTO roles (role)
SELECT 'Driver'
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE LOWER(role) = 'driver'
);

-- 2. Add sample drivers if they do not exist yet.
INSERT INTO employee (
    dept_ID,
    emp_firstname,
    emp_lastname,
    emp_contact,
    emp_email,
    emp_password,
    account_status,
    is_super_admin
)
SELECT 1, 'John', 'Arnellie', '09000000001', 'john.arnellie@dmac.local', '$2y$12$zMQHEGcdOLjeXmPU/fZXaOP5EOmV6EG.6GrNuqX.3qVt0286yTRwm', 'approved', 0
WHERE NOT EXISTS (SELECT 1 FROM employee WHERE emp_email = 'john.arnellie@dmac.local');

INSERT INTO employee (
    dept_ID,
    emp_firstname,
    emp_lastname,
    emp_contact,
    emp_email,
    emp_password,
    account_status,
    is_super_admin
)
SELECT 1, 'Marvin', 'Zoran', '09000000002', 'marvin.zoran@dmac.local', '$2y$12$zMQHEGcdOLjeXmPU/fZXaOP5EOmV6EG.6GrNuqX.3qVt0286yTRwm', 'approved', 0
WHERE NOT EXISTS (SELECT 1 FROM employee WHERE emp_email = 'marvin.zoran@dmac.local');

INSERT INTO employee (
    dept_ID,
    emp_firstname,
    emp_lastname,
    emp_contact,
    emp_email,
    emp_password,
    account_status,
    is_super_admin
)
SELECT 1, 'Mario', 'Bagusa', '09000000003', 'mario.bagusa@dmac.local', '$2y$12$zMQHEGcdOLjeXmPU/fZXaOP5EOmV6EG.6GrNuqX.3qVt0286yTRwm', 'approved', 0
WHERE NOT EXISTS (SELECT 1 FROM employee WHERE emp_email = 'mario.bagusa@dmac.local');

-- 3. Assign Driver role to the sample drivers in emprole.
INSERT INTO emprole (emp_ID, role_ID, is_coordinator)
SELECT e.emp_ID, r.role_ID, 0
FROM employee e
JOIN roles r ON LOWER(r.role) = 'driver'
WHERE e.emp_email IN (
    'john.arnellie@dmac.local',
    'marvin.zoran@dmac.local',
    'mario.bagusa@dmac.local'
)
AND NOT EXISTS (
    SELECT 1
    FROM emprole er
    WHERE er.emp_ID = e.emp_ID
      AND er.role_ID = r.role_ID
);

-- 4. Make sure transport table has fixed modes.
INSERT INTO transport (transport_ID, transport_mode)
VALUES (1, 'AIR')
ON DUPLICATE KEY UPDATE transport_mode = 'AIR';

INSERT INTO transport (transport_ID, transport_mode)
VALUES (2, 'LAND')
ON DUPLICATE KEY UPDATE transport_mode = 'LAND';

-- 5. Normalize active approved status if any old blank statuses exist.
UPDATE booking
SET booking_status = 'PENDING REVIEW'
WHERE booking_status IS NULL OR booking_status = '';
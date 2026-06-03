<?php
require_once __DIR__ . '/../../models/Employee.php';

class EmployeeController
{
    private Employee $employeeModel;
    private PDO $db;

    public function __construct(Employee $employeeModel, PDO $db)
    {
        $this->employeeModel = $employeeModel;
        $this->db = $db;
    }

    private function normalizeRoleIds(array $roleIds): array
    {
        $roleIds = array_filter(array_map('intval', $roleIds), fn($id) => $id > 0);
        return array_values(array_unique($roleIds));
    }

    private function getCoordinatorRoleId(): int
    {
        $stmt = $this->db->prepare("SELECT role_ID FROM roles WHERE LOWER(role) = 'coordinator' LIMIT 1");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function registerEmployee(array $input): array
    {
        $firstname = htmlspecialchars(trim($input['emp_firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lastname = htmlspecialchars(trim($input['emp_lastname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = filter_var(trim($input['emp_email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $contact = htmlspecialchars(trim($input['emp_contact'] ?? ''), ENT_QUOTES, 'UTF-8');
        $deptId = (int)($input['dept_id'] ?? 0);
        $password = trim($input['emp_password'] ?? '');
        $rawRoleIds = $input['roles'] ?? [];
        $roleIds = $this->normalizeRoleIds(is_array($rawRoleIds) ? $rawRoleIds : []);
        $isCoordinator = isset($input['is_coordinator']);

        if ($isCoordinator) {
            $coordRoleId = $this->getCoordinatorRoleId();
            if ($coordRoleId > 0 && !in_array($coordRoleId, $roleIds, true)) {
                $roleIds[] = $coordRoleId;
            }
        }

        if (empty($roleIds)) {
            $roleIds = [(int)($input['role_id'] ?? 0)];
        }
        $roleIds = $this->normalizeRoleIds($roleIds);

        if ($firstname === '' || $lastname === '' || $email === '' || $contact === '' || $password === '' || $deptId <= 0 || empty($roleIds)) {
            return ['success' => false, 'error' => 'missing'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'email'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'weak'];
        }

        if ($this->employeeModel->emailExists($email)) {
            return ['success' => false, 'error' => 'taken'];
        }

        $payload = [
            'dept_id' => $deptId,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'contact' => $contact,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role_ids' => $roleIds,
            'permission_ids' => is_array($input['permissions'] ?? []) ? array_values(array_unique(array_map('intval', $input['permissions']))) : [],
            'account_status' => 'approved',
            'is_coordinator' => $isCoordinator ? 1 : 0,
        ];

        $created = $this->employeeModel->registerEmployee($payload);
        if ($created === false) {
            return ['success' => false, 'error' => 'save'];
        }

        return ['success' => true, 'id' => $created];
    }

    public function updateEmployeeAccess(array $input): array
    {
        $empId = (int)($input['emp_id'] ?? 0);
        $firstname = htmlspecialchars(trim($input['emp_firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lastname = htmlspecialchars(trim($input['emp_lastname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $contact = htmlspecialchars(trim($input['emp_contact'] ?? ''), ENT_QUOTES, 'UTF-8');
        $deptId = (int)($input['dept_id'] ?? 0);
        $status = trim($input['account_status'] ?? 'approved');
        $rawRoleIds = $input['roles'] ?? [];
        $roleIds = $this->normalizeRoleIds(is_array($rawRoleIds) ? $rawRoleIds : []);
        $permissionIds = is_array($input['permissions'] ?? []) ? array_values(array_unique(array_map('intval', $input['permissions']))) : [];

        if ($empId <= 0 || $firstname === '' || $lastname === '' || $contact === '' || $deptId <= 0 || empty($roleIds)) {
            return ['success' => false, 'error' => 'missing'];
        }

        if (!in_array($status, ['approved', 'rejected'], true)) {
            $status = 'approved';
        }

        $updated = $this->employeeModel->updateEmployeeAccess($empId, [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'contact' => $contact,
            'dept_id' => $deptId,
            'role_ids' => $roleIds,
            'account_status' => $status,
            'permission_ids' => $permissionIds,
        ]);

        if (!$updated) {
            return ['success' => false, 'error' => 'update'];
        }

        return ['success' => true];
    }

    public function deactivateEmployee(int $targetEmpId, int $actingEmpId): array
    {
        if ($targetEmpId <= 0) {
            return ['success' => false, 'error' => 'invalid'];
        }

        if ($targetEmpId === $actingEmpId) {
            return ['success' => false, 'error' => 'self_delete'];
        }

        $stmt = $this->db->prepare("SELECT is_super_admin FROM employee WHERE emp_ID = :emp_id LIMIT 1");
        $stmt->execute(['emp_id' => $targetEmpId]);
        if ((int)$stmt->fetchColumn() === 1) {
            return ['success' => false, 'error' => 'protected'];
        }

        $update = $this->db->prepare("UPDATE employee SET account_status = 'rejected', deleted_at = NOW() WHERE emp_ID = :emp_id AND is_super_admin = 0");
        $result = $update->execute(['emp_id' => $targetEmpId]);

        return ['success' => $result, 'error' => $result ? null : 'server'];
    }
}

<?php

namespace Kanboard\Model;

use Kanboard\Core\Base;
use Kanboard\Core\Security\Role;

/**
 * Class ProjectRoleModel
 *
 * @package Kanboard\Model
 * @author  Frederic Guillot
 */
class ProjectRoleModel extends Base
{
    const TABLE = 'project_has_roles';

    /**
     * Get list of project roles
     *
     * @param  int $project_id
     * @return array
     */
    public function getList($project_id)
    {
        $defaultRoles = $this->role->getProjectRoles();
        $customRoles = $this->db
            ->hashtable(self::TABLE)
            ->eq('project_id', $project_id)
            ->getAll('role', 'role');

        return $defaultRoles + $customRoles;
    }

    /**
     * Get a role
     *
     * @param  int $project_id
     * @param  int $role_id
     * @return array|null
     */
    public function getById($project_id, $role_id)
    {
        return $this->db->table(self::TABLE)
            ->eq('project_id', $project_id)
            ->eq('role_id', $role_id)
            ->findOne();
    }

    /**
     * Get all project roles
     *
     * @param  int $project_id
     * @return array
     */
    public function getAll($project_id)
    {
        return $this->db->table(self::TABLE)
            ->eq('project_id', $project_id)
            ->asc('role')
            ->findAll();
    }

    /**
     * Get all project roles with restrictions
     *
     * @param  int $project_id
     * @return array
     */
    public function getAllWithRestrictions($project_id)
    {
        $roles = $this->getAll($project_id);

        $column_restrictions = $this->columnMoveRestrictionModel->getAll($project_id);
        $column_restrictions = array_column_index($column_restrictions, 'role_id');
        array_merge_relation($roles, $column_restrictions, 'column_restrictions', 'role_id');

        $project_restrictions = $this->projectRoleRestrictionModel->getAll($project_id);
        $project_restrictions = array_column_index($project_restrictions, 'role_id');
        array_merge_relation($roles, $project_restrictions, 'project_restrictions', 'role_id');

        return $roles;
    }

    /**
     * Create a new project role
     *
     * @param  int $project_id
     * @param  string $role
     * @return bool|int
     */
    public function create($project_id, $role)
    {
        return $this->db
            ->table(self::TABLE)
            ->persist(array(
                'project_id' => $project_id,
                'role' => $role,
            ));
    }

    /**
     * Update a project role
     *
     * @param  int $role_id
     * @param  int $project_id
     * @param  string $role
     * @return bool
     */
    public function update($role_id, $project_id, $role)
    {
        return $this->db
            ->table(self::TABLE)
            ->eq('role_id', $role_id)
            ->eq('project_id', $project_id)
            ->update(array(
                'role' => $role,
            ));
    }

    /**
     * Remove a project role
     *
     * @param  int $project_id
     * @param  int $role_id
     * @return bool
     */
    public function remove($project_id, $role_id)
    {
        $this->db->startTransaction();

        $role = $this->getById($project_id, $role_id);

        $r1 = $this->db
            ->table(ProjectUserRoleModel::TABLE)
            ->eq('project_id', $project_id)
            ->eq('role', $role['role'])
            ->update(array(
                'role' => Role::PROJECT_MEMBER
            ));

        $r2 = $this->db
            ->table(ProjectGroupRoleModel::TABLE)
            ->eq('project_id', $project_id)
            ->eq('role', $role['role'])
            ->update(array(
                'role' => Role::PROJECT_MEMBER
            ));

        $r3 = $this->db
            ->table(self::TABLE)
            ->eq('project_id', $project_id)
            ->eq('role_id', $role_id)
            ->remove();

        if ($r1 && $r2 && $r3) {
            $this->db->closeTransaction();
            return true;
        }

        $this->db->cancelTransaction();
        return false;
    }
}
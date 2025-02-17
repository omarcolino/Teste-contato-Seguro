<?php

namespace Contatoseguro\TesteBackend\Service;

use Contatoseguro\TesteBackend\Config\DB;

class CategoryService
{
    private \PDO $pdo;
    public function __construct()
    {
        $this->pdo = DB::connect();
    }

    public function getAll($adminUserId)
    {
           $query = "
            SELECT *
            FROM category c
            WHERE c.company_id = :company_id
        ";
    
        $stm = $this->pdo->prepare($query);
        $stm->bindValue(':company_id', $this->getCompanyFromAdminUser($adminUserId), \PDO::PARAM_INT);
        $stm->execute();

        return $stm->fetchAll();
    
    }

    public function getOne($adminUserId, $categoryId)
    {
        $query = "
            SELECT *
            FROM category c
            WHERE c.active = 1
            AND c.company_id = {$this->getCompanyFromAdminUser($adminUserId)}
            AND c.id = {$categoryId}
        ";

        $stm = $this->pdo->prepare($query);

        $stm->execute();

        return $stm;
    }

    public function getProductCategory($productId)
    {
        $query = "
            SELECT c.id
            FROM category c
            INNER JOIN product_category pc
                ON pc.cat_id = c.id
            WHERE pc.product_id = {$productId}
        ";

        $stm = $this->pdo->prepare($query);

        $stm->execute();

        return $stm;
    }

    public function insertOne($body, $adminUserId)
    {
        $stm = $this->pdo->prepare("
            INSERT INTO category (
                company_id,
                title,
                active
            ) VALUES (
                {$this->getCompanyFromAdminUser($adminUserId)},
                '{$body['title']}',
                {$body['active']}
            )
        ");

        return $stm->execute();
    }

    public function updateOne($id, $body, $adminUserId)
    {
        $active = (int)$body['active'];

        $stm = $this->pdo->prepare("
            UPDATE category
            SET title = '{$body['title']}',
                active = {$active}
            WHERE id = {$id}
            AND company_id = {$this->getCompanyFromAdminUser($adminUserId)}
        ");

        return $stm->execute();
    }

    public function deleteOne($id, $adminUserId)
    {
        $stm = $this->pdo->prepare("
            DELETE
            FROM category
            WHERE id = {$id}
            AND company_id = {$this->getCompanyFromAdminUser($adminUserId)}
        ");

        return $stm->execute();
    }

    private function getCompanyFromAdminUser($adminUserId)
    {
        try {
            $query = "
                SELECT company_id
                FROM admin_user
                WHERE id = :admin_user_id
            ";
            $stm = $this->pdo->prepare($query);
            $stm->bindValue(':admin_user_id', $adminUserId, \PDO::PARAM_INT);
            $stm->execute();
    
            $result = $stm->fetch(\PDO::FETCH_ASSOC);
    
            if ($result && isset($result['company_id'])) {
                return $result['company_id'];
            }
    
            throw new \Exception("Admin user with ID {$adminUserId} not found");
    
        } catch (\PDOException $e) {
            throw new \Exception("Database error: " . $e->getMessage());
        }
    }
}

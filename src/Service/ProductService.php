<?php

namespace Contatoseguro\TesteBackend\Service;

use Contatoseguro\TesteBackend\Config\DB;
use Exception;

class ProductService
{
    private \PDO $pdo;
    public function __construct()
    {
        $this->pdo = DB::connect();
    }

    public function getAll($adminUserId, $filters = [])
    {
        try {
            $query = <<<SQL
                SELECT p.*, c.title as category
                FROM product p
                INNER JOIN product_category pc ON pc.product_id = p.id
                INNER JOIN category c ON c.id = pc.cat_id
                WHERE p.company_id = :companyId
            SQL;

            if (isset($filters['status'])) {
                $query .= " AND p.active = :status";
            }

            if (isset($filters['category_id'])) {
                $query .= " AND c.id = :category_id";
            }

            if (isset($filters['order_by_date'])) {
                $query .= " ORDER BY p.created_at " . ($filters['order_by_date'] === 'desc' ? 'DESC' : 'ASC');
            }

            $stm = $this->pdo->prepare($query);
            $stm->bindParam(':companyId', $adminUserId, \PDO::PARAM_INT);

            if (isset($filters['status'])) {
                $stm->bindParam(':status', $filters['status'], \PDO::PARAM_INT);
            }

            if (isset($filters['category_id'])) {
                $stm->bindParam(':category_id', $filters['category_id'], \PDO::PARAM_INT);
            }

            $stm->execute();

            return $stm->fetchAll(\PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['error' => 'Erro ao obter os produtos: ' . $e->getMessage()];
        }
    }

    public function getOne($id)
    {
        $query = <<<SQL
            SELECT p.*, c.title as category
            FROM product p
            LEFT JOIN product_category pc ON pc.product_id = p.id
            LEFT JOIN category c ON c.id = pc.cat_id
            WHERE p.id = :id
        SQL;

        $stm = $this->pdo->prepare($query);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->execute();

        return $stm->fetch();
    }

    public function insertOne($body, $adminUserId)
    {
        $stm = $this->pdo->prepare(
            <<<SQL
            INSERT INTO product (
                company_id,
                title,
                price,
                active
            ) VALUES (
                :company_id,
                :title,
                :price,
                :active
            )
            SQL
        );
            $stm->bindParam(':company_id', $body['company_id'], \PDO::PARAM_INT);
            $stm->bindParam(':title', $body['title'], \PDO::PARAM_STR);
            $stm->bindParam(':price', $body['price'], \PDO::PARAM_STR);
            $stm->bindParam(':active', $body['active'], \PDO::PARAM_INT);
            if (!$stm->execute()) {
                return false;
            }

        $productId = $this->pdo->lastInsertId();

        $stm = $this->pdo->prepare(
            <<<SQL
            INSERT INTO product_category (
                product_id,
                cat_id
            ) VALUES (
                :product_id,
                :cat_id
            );
            SQL
        );
        $stm->bindParam(':product_id', $productId, \PDO::PARAM_INT);
        $stm->bindParam(':cat_id', $body['category_id'], \PDO::PARAM_INT);
        if (!$stm->execute()) {
            return false;
        }

        $stm = $this->pdo->prepare(
            <<<SQL
            INSERT INTO product_log (
                product_id,
                admin_user_id,
                `action`
            ) VALUES (
                :product_id,
                :admin_user_id,
                'create'
            )
            SQL
        );
        $stm->bindParam(':product_id', $productId, \PDO::PARAM_INT);
        $stm->bindParam(':admin_user_id', $adminUserId, \PDO::PARAM_INT);

        return $stm->execute();
    }


    public function updateOne($id, $body, $adminUserId)
    {
        $query = <<<SQL
            UPDATE product
            SET company_id = :company_id,
                title = :title,
                price = :price,
                active = :active
            WHERE id = :id
        SQL;

        $stm = $this->pdo->prepare($query);
        $stm->bindParam(':company_id', $body['company_id'], \PDO::PARAM_INT);
        $stm->bindParam(':title', $body['title'], \PDO::PARAM_STR);
        $stm->bindParam(':price', $body['price'], \PDO::PARAM_STR);
        $stm->bindParam(':active', $body['active'], \PDO::PARAM_INT);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        if (!$stm->execute()) {
            return false;
        }

        $queryCategory = <<<SQL
            UPDATE product_category
            SET cat_id = :category_id
            WHERE product_id = :id
        SQL;

        $stm = $this->pdo->prepare($queryCategory);
        $stm->bindParam(':category_id', $body['category_id'], \PDO::PARAM_INT);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        if (!$stm->execute()) {
            return false;
        }

        $queryLog = <<<SQL
            INSERT INTO product_log (
                product_id,
                admin_user_id,
                `action`
            ) VALUES (
                :id,
                :admin_user_id,
                'update'
            )
        SQL;

        $stm = $this->pdo->prepare($queryLog);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':admin_user_id', $adminUserId, \PDO::PARAM_INT);

        return $stm->execute();
    }

    public function deleteOne($id, $adminUserId)
    {
        $query = <<<SQL
            DELETE FROM product_category WHERE product_id = :id
        SQL;
    
        $stm = $this->pdo->prepare($query);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        if (!$stm->execute()) {
            return false;
        }
    
        $queryProduct = <<<SQL
            DELETE FROM product WHERE id = :id
        SQL;
    
        $stm = $this->pdo->prepare($queryProduct);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        if (!$stm->execute()) {
            return false;
        }
    
        $queryLog = <<<SQL
            INSERT INTO product_log (
                product_id,
                admin_user_id,
                `action`
            ) VALUES (
                :id,
                :admin_user_id,
                'delete'
            )
        SQL;
    
        $stm = $this->pdo->prepare($queryLog);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':admin_user_id', $adminUserId, \PDO::PARAM_INT);
    
        return $stm->execute();
    }

    public function getLog($id)
    {
        try {
            $query = <<<SQL
                SELECT 
                    u.name AS user_name,
                    pl.action AS action_type,
                    pl.timestamp AS action_date
                FROM product_log pl
                JOIN admin_user u ON u.id = pl.admin_user_id
                WHERE pl.product_id = :id  
                AND pl.action = 'update'
                ORDER BY pl.timestamp DESC
            SQL;
    
            $stm = $this->pdo->prepare($query);
            $stm->bindParam(':id', $id, \PDO::PARAM_INT);
            $stm->execute();
    
            $logs = $stm->fetchAll(\PDO::FETCH_ASSOC);
    
            if (empty($logs)) {
                return "Não há logs para este produto.";
            }
    
            $formattedLogs = [];
            foreach ($logs as $log) {
                $formattedLogs[] = "(" . $log['user_name'] . ", " . ucfirst($log['action_type']) . ", " . date('d/m/Y H:i:s', strtotime($log['action_date'])) . ")";
            }
    
            return implode(", ", $formattedLogs);
    
        } catch (\PDOException $e) {
            return "Erro ao recuperar os logs: " . $e->getMessage();
        }
    }
}

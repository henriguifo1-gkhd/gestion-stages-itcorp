<?php

namespace App\Core;

use PDO;

/**
 * Modèle de base : requêtes CRUD génériques + pagination AJAX (20 lignes max).
 */
abstract class Model
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(?string $orderBy = null): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        return $this->db->query($sql)->fetchAll();
    }

    public function insert(array $data): int
    {
        $cols = array_keys($data);
        $place = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO {$this->table} (" . implode(',', $cols) . ") VALUES (" . implode(',', $place) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sets = implode(',', array_map(fn($c) => "$c = :$c", array_keys($data)));
        $sql = "UPDATE {$this->table} SET {$sets} WHERE {$this->primaryKey} = :__id";
        $data['__id'] = $id;
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Pagination générique utilisée par tous les endpoints AJAX.
     * Maximum 20 lignes par page (configurable, plafonné à 20).
     *
     * @param string $baseSelect  ex: "SELECT s.*, u.nom AS encadrant_nom FROM stagiaire s ..."
     * @param string $baseFrom    ex: "FROM stagiaire s LEFT JOIN ..."
     * @param array  $wheres      tableau de fragments SQL "col = :nom"
     * @param array  $params      paramètres liés aux fragments where
     * @param int    $page
     * @param int    $perPage
     * @param string $orderBy
     */
    public function paginateRaw(string $select, string $from, array $wheres, array $params, int $page, int $perPage, string $orderBy): array
    {
        $perPage = max(1, min(20, $perPage)); // plafond imposé par le cahier des charges
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $whereSql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS c {$from} {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetch()['c'];

        $dataSql = "{$select} {$from} {$whereSql} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($dataSql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'data'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage) ?: 1,
        ];
    }
}

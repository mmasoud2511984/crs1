<?php
/**
 * File: Model.php
 * Path: /core/Model.php
 * Purpose: Base Model class for all models with CRUD operations
 * Dependencies: Database.php
 */

namespace Core;

use Exception;

/**
 * Model Class
 * كلاس Model الأساسي - يوفر عمليات CRUD أساسية
 */
abstract class Model
{
    /** @var string اسم الجدول */
    protected string $table;
    
    /** @var string المفتاح الأساسي */
    protected string $primaryKey = 'id';
    
    /** @var array الحقول القابلة للملء */
    protected array $fillable = [];
    
    /** @var array الحقول المحمية */
    protected array $guarded = ['id', 'created_at', 'updated_at'];
    
    /** @var bool استخدام soft deletes */
    protected bool $softDelete = false;
    
    /** @var array شروط WHERE */
    private array $wheres = [];
    
    /** @var array ORDER BY */
    private array $orders = [];
    
    /** @var int|null LIMIT */
    private ?int $limit = null;
    
    /** @var int OFFSET */
    private int $offset = 0;
    
    /**
     * البحث عن سجل بواسطة المفتاح الأساسي
     * 
     * @param int $id
     * @return array|null
     */
    public function find(int $id): ?array
    {
        $query = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        
        if ($this->softDelete) {
            $query .= " AND deleted_at IS NULL";
        }
        
        return Database::selectOne($query, [$id]);
    }
    
    /**
     * البحث عن سجل بواسطة عمود معين
     * 
     * @param string $column
     * @param mixed $value
     * @return array|null
     */
    public function findBy(string $column, $value): ?array
    {
        $query = "SELECT * FROM {$this->table} WHERE {$column} = ?";
        
        if ($this->softDelete) {
            $query .= " AND deleted_at IS NULL";
        }
        
        return Database::selectOne($query, [$value]);
    }
    
    /**
     * الحصول على جميع السجلات
     * 
     * @return array
     */
    public function all(): array
    {
        $query = "SELECT * FROM {$this->table}";
        
        if ($this->softDelete) {
            $query .= " WHERE deleted_at IS NULL";
        }
        
        return Database::select($query);
    }
    
    /**
     * إضافة شرط WHERE
     * 
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return self
     */
    public function where(string $column, string $operator, $value): self
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];
        
        return $this;
    }
    
    /**
     * إضافة ORDER BY
     * 
     * @param string $column
     * @param string $direction
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];
        
        return $this;
    }
    
    /**
     * تحديد LIMIT
     * 
     * @param int $limit
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * تحديد OFFSET
     * 
     * @param int $offset
     * @return self
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }
    
    /**
     * تنفيذ الاستعلام والحصول على النتائج
     * 
     * @return array
     */
    public function get(): array
    {
        $query = "SELECT * FROM {$this->table}";
        $params = [];
        
        // إضافة WHERE
        if (!empty($this->wheres)) {
            $conditions = [];
            foreach ($this->wheres as $where) {
                $conditions[] = "{$where['column']} {$where['operator']} ?";
                $params[] = $where['value'];
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
            
            if ($this->softDelete) {
                $query .= " AND deleted_at IS NULL";
            }
        } elseif ($this->softDelete) {
            $query .= " WHERE deleted_at IS NULL";
        }
        
        // إضافة ORDER BY
        if (!empty($this->orders)) {
            $orderClauses = [];
            foreach ($this->orders as $order) {
                $orderClauses[] = "{$order['column']} {$order['direction']}";
            }
            $query .= " ORDER BY " . implode(', ', $orderClauses);
        }
        
        // إضافة LIMIT و OFFSET
        if ($this->limit !== null) {
            $query .= " LIMIT {$this->limit}";
            if ($this->offset > 0) {
                $query .= " OFFSET {$this->offset}";
            }
        }
        
        $results = Database::select($query, $params);
        
        // إعادة تعيين
        $this->reset();
        
        return $results;
    }
    
    /**
     * الحصول على أول سجل
     * 
     * @return array|null
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }
    
    /**
     * إنشاء سجل جديد
     * 
     * @param array $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        // تصفية البيانات
        $data = $this->filterData($data);
        
        if (empty($data)) {
            return false;
        }
        
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $query = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        return Database::insert($query, array_values($data));
    }
    
    /**
     * تحديث سجل
     * 
     * @param int $id
     * @param array $data
     * @return int
     */
    public function update(int $id, array $data): int
    {
        // تصفية البيانات
        $data = $this->filterData($data);
        
        if (empty($data)) {
            return 0;
        }
        
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "{$column} = ?";
        }
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE {$this->primaryKey} = ?";
        $params = array_values($data);
        $params[] = $id;
        
        return Database::update($query, $params);
    }
    
    /**
     * حذف سجل
     * 
     * @param int $id
     * @return int
     */
    public function delete(int $id): int
    {
        if ($this->softDelete) {
            return $this->softDelete($id);
        }
        
        $query = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return Database::delete($query, [$id]);
    }
    
    /**
     * حذف ناعم
     * 
     * @param int $id
     * @return int
     */
    public function softDelete(int $id): int
    {
        $query = "UPDATE {$this->table} SET deleted_at = NOW() WHERE {$this->primaryKey} = ?";
        return Database::update($query, [$id]);
    }
    
    /**
     * استعادة سجل محذوف ناعماً
     * 
     * @param int $id
     * @return int
     */
    public function restore(int $id): int
    {
        $query = "UPDATE {$this->table} SET deleted_at = NULL WHERE {$this->primaryKey} = ?";
        return Database::update($query, [$id]);
    }
    
    /**
     * تصفية البيانات
     * 
     * @param array $data
     * @return array
     */
    private function filterData(array $data): array
    {
        $filtered = [];
        
        foreach ($data as $key => $value) {
            // تخطي الحقول المحمية
            if (in_array($key, $this->guarded)) {
                continue;
            }
            
            // إذا كان هناك fillable، استخدمه فقط
            if (!empty($this->fillable) && !in_array($key, $this->fillable)) {
                continue;
            }
            
            $filtered[$key] = $value;
        }
        
        return $filtered;
    }
    
    /**
     * إعادة تعيين الشروط
     * 
     * @return void
     */
    private function reset(): void
    {
        $this->wheres = [];
        $this->orders = [];
        $this->limit = null;
        $this->offset = 0;
    }
    
    /**
     * عد السجلات
     * 
     * @return int
     */
    public function count(): int
    {
        $condition = $this->softDelete ? 'deleted_at IS NULL' : '';
        return Database::count($this->table, $condition);
    }
}

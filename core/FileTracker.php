<?php
/**
 * File: FileTracker.php
 * Path: /core/FileTracker.php
 * Purpose: Smart file tracking system to log all file modifications
 * Dependencies: None
 */

namespace Core;

/**
 * FileTracker Class
 * نظام تتبع ذكي للملفات - يسجل جميع التعديلات تلقائياً
 */
class FileTracker
{
    /** @var string مسار ملف السجل */
    private const LOG_FILE = __DIR__ . '/../FILE_CHANGES.log';
    
    /** @var string أنواع العمليات */
    private const ACTION_CREATE = 'CREATE';
    private const ACTION_MODIFY = 'MODIFY';
    private const ACTION_DELETE = 'DELETE';
    
    /**
     * تسجيل إنشاء ملف جديد
     * 
     * @param string $filePath مسار الملف
     * @param int $lineCount عدد الأسطر
     * @param string $changedBy من قام بالإنشاء
     * @return bool
     */
    public static function logCreate(string $filePath, int $lineCount = 0, string $changedBy = 'System'): bool
    {
        return self::log(self::ACTION_CREATE, $filePath, 0, $lineCount, $changedBy);
    }
    
    /**
     * تسجيل تعديل ملف موجود
     * 
     * @param string $filePath مسار الملف
     * @param int $oldLines عدد الأسطر القديمة
     * @param int $newLines عدد الأسطر الجديدة
     * @param string $changedBy من قام بالتعديل
     * @return bool
     */
    public static function logModify(string $filePath, int $oldLines, int $newLines, string $changedBy = 'System'): bool
    {
        return self::log(self::ACTION_MODIFY, $filePath, $oldLines, $newLines, $changedBy);
    }
    
    /**
     * تسجيل حذف ملف
     * 
     * @param string $filePath مسار الملف
     * @param int $lineCount عدد الأسطر المحذوفة
     * @param string $changedBy من قام بالحذف
     * @return bool
     */
    public static function logDelete(string $filePath, int $lineCount = 0, string $changedBy = 'System'): bool
    {
        return self::log(self::ACTION_DELETE, $filePath, $lineCount, 0, $changedBy);
    }
    
    /**
     * تسجيل العملية في ملف السجل
     * 
     * @param string $action نوع العملية
     * @param string $filePath مسار الملف
     * @param int $oldLines عدد الأسطر القديمة
     * @param int $newLines عدد الأسطر الجديدة
     * @param string $changedBy من قام بالعملية
     * @return bool
     */
    private static function log(
        string $action,
        string $filePath,
        int $oldLines,
        int $newLines,
        string $changedBy
    ): bool {
        try {
            $cleanPath = self::cleanPath($filePath);
            $timestamp = date('Y-m-d H:i:s');
            
            $logLine = sprintf(
                "[%s] | %-8s | %-60s | %4d | %4d | %s\n",
                $timestamp,
                $action,
                $cleanPath,
                $oldLines,
                $newLines,
                $changedBy
            );
            
            // التأكد من وجود المجلد
            $logDir = dirname(self::LOG_FILE);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            return file_put_contents(self::LOG_FILE, $logLine, FILE_APPEND | LOCK_EX) !== false;
            
        } catch (\Exception $e) {
            error_log("FileTracker Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تنظيف مسار الملف
     * 
     * @param string $filePath المسار الكامل
     * @return string المسار النسبي
     */
    private static function cleanPath(string $filePath): string
    {
        $projectRoot = dirname(__DIR__);
        $cleanPath = str_replace($projectRoot, '', $filePath);
        return ltrim($cleanPath, '/\\');
    }
    
    /**
     * عد أسطر ملف
     * 
     * @param string $filePath مسار الملف
     * @return int
     */
    public static function countLines(string $filePath): int
    {
        if (!file_exists($filePath)) {
            return 0;
        }
        
        $lineCount = 0;
        $handle = fopen($filePath, 'r');
        
        if ($handle) {
            while (!feof($handle)) {
                fgets($handle);
                $lineCount++;
            }
            fclose($handle);
        }
        
        return $lineCount;
    }
    
    /**
     * الحصول على آخر سجلات
     * 
     * @param int $count عدد السجلات
     * @return array
     */
    public static function getRecentLogs(int $count = 50): array
    {
        if (!file_exists(self::LOG_FILE)) {
            return [];
        }
        
        $lines = file(self::LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];
        
        // تخطي الترويسة (أول 10 أسطر)
        $dataLines = array_slice($lines, 10);
        $recentLines = array_slice($dataLines, -$count);
        
        foreach ($recentLines as $line) {
            if (preg_match('/\[(.*?)\]\s*\|\s*(.*?)\s*\|\s*(.*?)\s*\|\s*(\d+)\s*\|\s*(\d+)\s*\|\s*(.*)/', $line, $matches)) {
                $logs[] = [
                    'timestamp' => trim($matches[1]),
                    'action' => trim($matches[2]),
                    'file' => trim($matches[3]),
                    'old_lines' => (int)$matches[4],
                    'new_lines' => (int)$matches[5],
                    'changed_by' => trim($matches[6])
                ];
            }
        }
        
        return array_reverse($logs);
    }
    
    /**
     * الحصول على إحصائيات
     * 
     * @return array
     */
    public static function getStats(): array
    {
        $logs = self::getRecentLogs(1000);
        
        $stats = [
            'total' => count($logs),
            'created' => 0,
            'modified' => 0,
            'deleted' => 0,
            'total_lines_added' => 0,
            'total_lines_removed' => 0,
            'files' => []
        ];
        
        foreach ($logs as $log) {
            switch ($log['action']) {
                case self::ACTION_CREATE:
                    $stats['created']++;
                    $stats['total_lines_added'] += $log['new_lines'];
                    break;
                    
                case self::ACTION_MODIFY:
                    $stats['modified']++;
                    $diff = $log['new_lines'] - $log['old_lines'];
                    if ($diff > 0) {
                        $stats['total_lines_added'] += $diff;
                    } else {
                        $stats['total_lines_removed'] += abs($diff);
                    }
                    break;
                    
                case self::ACTION_DELETE:
                    $stats['deleted']++;
                    $stats['total_lines_removed'] += $log['old_lines'];
                    break;
            }
            
            if (!in_array($log['file'], $stats['files'])) {
                $stats['files'][] = $log['file'];
            }
        }
        
        $stats['unique_files'] = count($stats['files']);
        return $stats;
    }
    
    /**
     * إنشاء تقرير HTML
     * 
     * @param int $count عدد السجلات
     * @return string
     */
    public static function generateReport(int $count = 50): string
    {
        $logs = self::getRecentLogs($count);
        $stats = self::getStats();
        
        $html = '<div class="file-tracker-report" dir="rtl">';
        $html .= '<h2>📊 تقرير تتبع الملفات</h2>';
        
        // الإحصائيات
        $html .= '<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">';
        $html .= '<div class="stat-card" style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">';
        $html .= '<h3 style="margin: 0; font-size: 32px; color: #333;">' . $stats['total'] . '</h3>';
        $html .= '<p style="margin: 10px 0 0; color: #666;">إجمالي العمليات</p></div>';
        
        $html .= '<div class="stat-card" style="background: #d4edda; padding: 20px; border-radius: 8px; text-align: center;">';
        $html .= '<h3 style="margin: 0; font-size: 32px; color: #155724;">' . $stats['created'] . '</h3>';
        $html .= '<p style="margin: 10px 0 0; color: #155724;">ملفات منشأة</p></div>';
        
        $html .= '<div class="stat-card" style="background: #fff3cd; padding: 20px; border-radius: 8px; text-align: center;">';
        $html .= '<h3 style="margin: 0; font-size: 32px; color: #856404;">' . $stats['modified'] . '</h3>';
        $html .= '<p style="margin: 10px 0 0; color: #856404;">ملفات معدلة</p></div>';
        
        $html .= '<div class="stat-card" style="background: #f8d7da; padding: 20px; border-radius: 8px; text-align: center;">';
        $html .= '<h3 style="margin: 0; font-size: 32px; color: #721c24;">' . $stats['deleted'] . '</h3>';
        $html .= '<p style="margin: 10px 0 0; color: #721c24;">ملفات محذوفة</p></div>';
        $html .= '</div>';
        
        // السجلات
        $html .= '<h3>آخر ' . $count . ' عملية</h3>';
        $html .= '<table class="log-table" style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        $html .= '<thead><tr style="background: #343a40; color: white;">';
        $html .= '<th style="padding: 12px; text-align: right; border: 1px solid #dee2e6;">التاريخ والوقت</th>';
        $html .= '<th style="padding: 12px; text-align: center; border: 1px solid #dee2e6;">العملية</th>';
        $html .= '<th style="padding: 12px; text-align: right; border: 1px solid #dee2e6;">الملف</th>';
        $html .= '<th style="padding: 12px; text-align: center; border: 1px solid #dee2e6;">الأسطر القديمة</th>';
        $html .= '<th style="padding: 12px; text-align: center; border: 1px solid #dee2e6;">الأسطر الجديدة</th>';
        $html .= '<th style="padding: 12px; text-align: center; border: 1px solid #dee2e6;">التغيير</th>';
        $html .= '<th style="padding: 12px; text-align: right; border: 1px solid #dee2e6;">بواسطة</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($logs as $log) {
            $actionColor = match($log['action']) {
                'CREATE' => '#28a745',
                'MODIFY' => '#ffc107',
                'DELETE' => '#dc3545',
                default => '#6c757d'
            };
            
            $diff = $log['new_lines'] - $log['old_lines'];
            $diffText = $diff > 0 ? '+' . $diff : $diff;
            $diffColor = $diff > 0 ? '#28a745' : ($diff < 0 ? '#dc3545' : '#6c757d');
            
            $html .= '<tr style="border-bottom: 1px solid #dee2e6;">';
            $html .= '<td style="padding: 10px;">' . htmlspecialchars($log['timestamp']) . '</td>';
            $html .= '<td style="padding: 10px; text-align: center;"><span style="background: ' . $actionColor . '; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">' . htmlspecialchars($log['action']) . '</span></td>';
            $html .= '<td style="padding: 10px;"><code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px;">' . htmlspecialchars($log['file']) . '</code></td>';
            $html .= '<td style="padding: 10px; text-align: center;">' . $log['old_lines'] . '</td>';
            $html .= '<td style="padding: 10px; text-align: center;">' . $log['new_lines'] . '</td>';
            $html .= '<td style="padding: 10px; text-align: center; color: ' . $diffColor . '; font-weight: bold;">' . $diffText . '</td>';
            $html .= '<td style="padding: 10px;">' . htmlspecialchars($log['changed_by']) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>';
        
        return $html;
    }
}

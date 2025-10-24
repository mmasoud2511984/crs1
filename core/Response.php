<?php
/**
 * File: Response.php
 * Path: /core/Response.php
 * Purpose: HTTP Response handler
 * Dependencies: None
 */

namespace Core;

/**
 * Response Class
 * معالجة استجابات HTTP
 */
class Response
{
    /** @var int كود الحالة */
    private int $statusCode = 200;
    
    /** @var array Headers */
    private array $headers = [];
    
    /** @var string المحتوى */
    private string $content = '';
    
    /**
     * إنشاء استجابة JSON
     * 
     * @param array $data
     * @param int $status
     * @return self
     */
    public static function json(array $data, int $status = 200): self
    {
        $response = new self();
        $response->setStatusCode($status);
        $response->setHeader('Content-Type', 'application/json');
        $response->content = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        return $response;
    }
    
    /**
     * إنشاء استجابة HTML
     * 
     * @param string $content
     * @param int $status
     * @return self
     */
    public static function html(string $content, int $status = 200): self
    {
        $response = new self();
        $response->setStatusCode($status);
        $response->setHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->content = $content;
        
        return $response;
    }
    
    /**
     * تحميل ملف
     * 
     * @param string $file
     * @param string|null $name
     * @return void
     */
    public static function download(string $file, ?string $name = null): void
    {
        if (!file_exists($file)) {
            http_response_code(404);
            exit('File not found');
        }
        
        $name = $name ?? basename($file);
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($file));
        
        readfile($file);
        exit;
    }
    
    /**
     * إعادة توجيه
     * 
     * @param string $url
     * @param int $status
     * @return void
     */
    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * العودة للصفحة السابقة
     * 
     * @return void
     */
    public static function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        self::redirect($referer);
    }
    
    /**
     * تعيين كود الحالة
     * 
     * @param int $code
     * @return self
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }
    
    /**
     * تعيين header
     * 
     * @param string $key
     * @param string $value
     * @return self
     */
    public function setHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }
    
    /**
     * إرسال الاستجابة
     * 
     * @return void
     */
    public function send(): void
    {
        http_response_code($this->statusCode);
        
        foreach ($this->headers as $key => $value) {
            header($key . ': ' . $value);
        }
        
        echo $this->content;
        exit;
    }
}

<?php
/**
 * File: Controller.php
 * Path: /core/Controller.php
 * Purpose: Base Controller class for all controllers
 * Dependencies: View.php, Response.php, Session.php
 */

namespace Core;

/**
 * Controller Class
 * كلاس Controller الأساسي
 */
abstract class Controller
{
    /** @var array البيانات المشتركة للعرض */
    protected array $data = [];
    
    /** @var array Middleware */
    protected array $middleware = [];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // يمكن تنفيذ middleware هنا
        $this->executeMiddleware();
    }
    
    /**
     * عرض View
     * 
     * @param string $view
     * @param array $data
     * @return void
     */
    protected function view(string $view, array $data = []): void
    {
        $data = array_merge($this->data, $data);
        View::render($view, $data);
    }
    
    /**
     * إرجاع استجابة JSON
     * 
     * @param array $data
     * @param int $statusCode
     * @return void
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        Response::json($data, $statusCode)->send();
    }
    
    /**
     * إعادة توجيه
     * 
     * @param string $url
     * @param int $statusCode
     * @return void
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        Response::redirect($url, $statusCode);
    }
    
    /**
     * العودة للصفحة السابقة
     * 
     * @return void
     */
    protected function back(): void
    {
        Response::back();
    }
    
    /**
     * إضافة middleware
     * 
     * @param string $middleware
     * @return void
     */
    protected function middleware(string $middleware): void
    {
        $this->middleware[] = $middleware;
    }
    
    /**
     * تنفيذ middleware
     * 
     * @return void
     */
    private function executeMiddleware(): void
    {
        foreach ($this->middleware as $middleware) {
            // تنفيذ middleware
            // يمكن تحسينه لاحقاً
        }
    }
    
    /**
     * التحقق من الصلاحية
     * 
     * @param string $permission
     * @return bool
     */
    protected function authorize(string $permission): bool
    {
        if (!Auth::check()) {
            $this->redirect('/login');
            return false;
        }
        
        if (!Auth::hasPermission($permission)) {
            $this->json(['error' => 'غير مصرح'], 403);
            return false;
        }
        
        return true;
    }
    
    /**
     * إضافة بيانات مشتركة
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function share(string $key, $value): void
    {
        $this->data[$key] = $value;
    }
}

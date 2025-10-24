<?php
/**
 * Simple Test Index - Car Rental System
 */

echo "<!DOCTYPE html>";
echo "<html lang='ar' dir='rtl'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>نظام تأجير السيارات</title>";
echo "<style>";
echo "* { margin: 0; padding: 0; box-sizing: border-box; }";
echo "body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }";
echo ".container { background: white; padding: 60px 40px; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 600px; text-align: center; }";
echo "h1 { color: #2c3e50; font-size: 36px; margin-bottom: 20px; }";
echo ".success { color: #27ae60; font-size: 48px; margin-bottom: 20px; }";
echo "p { color: #7f8c8d; font-size: 18px; line-height: 1.6; margin-bottom: 30px; }";
echo ".info { background: #ecf0f1; padding: 20px; border-radius: 10px; margin-top: 30px; text-align: right; }";
echo ".info h3 { color: #2c3e50; margin-bottom: 10px; }";
echo ".info ul { list-style: none; }";
echo ".info li { padding: 5px 0; color: #34495e; }";
echo ".btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 40px; border-radius: 50px; text-decoration: none; margin-top: 20px; font-size: 16px; transition: transform 0.3s; }";
echo ".btn:hover { transform: translateY(-2px); }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";
echo "<div class='success'>✅</div>";
echo "<h1>مرحباً في نظام تأجير السيارات</h1>";
echo "<p>تم تحميل النظام بنجاح! PHP يعمل بشكل صحيح.</p>";

// معلومات النظام
echo "<div class='info'>";
echo "<h3>📊 معلومات النظام</h3>";
echo "<ul>";
echo "<li>✅ PHP Version: " . phpversion() . "</li>";
echo "<li>✅ الوقت: " . date('Y-m-d H:i:s') . "</li>";
echo "<li>✅ المسار: " . __DIR__ . "</li>";
echo "<li>✅ الخادم: " . $_SERVER['SERVER_SOFTWARE'] . "</li>";
echo "</ul>";
echo "</div>";

// التالي
echo "<div class='info'>";
echo "<h3>🎯 الخطوات التالية</h3>";
echo "<ul>";
echo "<li>✅ Phase 1: النظام الأساسي جاهز</li>";
echo "<li>⏳ Phase 2: Settings & Administration (التالي)</li>";
echo "<li>⏳ Phase 3: إدارة المستخدمين</li>";
echo "<li>⏳ Phase 4: إدارة السيارات</li>";
echo "</ul>";
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
<?php

ob_start();

// البيانات الخاصة بك
$token = "8644606730:AAFYoldtVgXOnMkCzZJJhjS6-_8tnUvdrZY"; 
$admin_id = "7498476611"; 

// مفتاح Gemini API الافتراضي للتشغيل
$gemini_api_key = "AIzaSy" . "A-YOUR-KEY-HERE"; 

define('API_KEY', $token);

// دالة إرسال الرسائل للتليجرام
function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res);
}

// دالة الاتصال بذكاء Gemini
function ask_gemini($question, $api_key) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
    
    $data = [
        "contents" => [
            ["parts" => [["text" => $question]]]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }
    return "عذراً، واجهت مشكلة في معالجة الطلب حالياً.";
}

// إنشاء المجلدات وحفظ البيانات
if(!file_exists("bot_data")) {
    mkdir("bot_data");
}

// جلب الإعدادات المخزونة
$members = explode("\n", @file_get_contents("bot_data/members.txt"));
$ban_list = explode("\n", @file_get_contents("bot_data/ban.txt"));
$welcome_msg = @file_get_contents("bot_data/welcome.txt");
if(empty($welcome_msg)) {
    $welcome_msg = "◼ مرحبا بك في بوت الذكاء الاصطناعي الخاص بقناتنا.\n\n◼ ارسل سؤالك مباشرة وسيقوم البوت بالرد عليك ومساعدتك فوراً 🚀";
}
$action = @file_get_contents("bot_data/action_$admin_id.txt");

// استقبال التحديثات
$update = json_decode(file_get_contents("php://input"));

if (isset($update->callback_query)) {
    $up = $update->callback_query;
    $chat_id = $up->message->chat->id;
    $from_id = $up->from->id;
    $message_id = $up->message->message_id;
    $data = $up->data;
} elseif (isset($update->message)) {
    $message = $update->message;
    $text = $message->text;
    $chat_id = $message->chat->id;
    $from_id = $message->from->id;
    $message_id = $message->message_id;
} else {
    exit;
}

// حفظ الأعضاء الجدد
if (isset($message) && !in_array($from_id, $members)) {
    file_put_contents("bot_data/members.txt", $from_id . "\n", FILE_APPEND);
    $members[] = $from_id;
}

// التحقق من الحظر
if (in_array($from_id, $ban_list) && $from_id != $admin_id) {
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "❌ نأسف، لقد تم حظرك من استخدام هذا البوت من قبل الإدارة.",
        'reply_to_message_id' => $message_id
    ]);
    exit;
}

// أوامر الأدمن الأساسية
if ($text == "/admin" && $from_id == $admin_id) {
    file_put_contents("bot_data/action_$admin_id.txt", "null");
    $count_members = count(array_filter($members));
    $count_bans = count(array_filter($ban_list));
    
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "👑 أهلاً بك يا مدير في لوحة التحكم الخاصة بك.\n\n📊 الإحصائيات:\n• عدد الأعضاء: $count_members\n• عدد المحظورين: $count_bans",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "📝 تعديل كليشة الترحيب", 'callback_data' => "set_welcome"]],
                [['text' => "🚫 حظر عضو", 'callback_data' => "ban_user"], ['text' => "✅ إلغاء حظر عضو", 'callback_data' => "unban_user"]]
            ]
        ])
    ]);
    exit;
}

// معالجة ضغطات أزرار لوحة التحكم
if (isset($data) && $from_id == $admin_id) {
    if ($data == "set_welcome") {
        file_put_contents("bot_data/action_$admin_id.txt", "wait_welcome");
        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "📥 أرسل الآن كليشة الترحيب الجديدة التي تريدها أن تظهر للمستخدمين عند إرسال /start:"
        ]);
    }
    if ($data == "ban_user") {
        file_put_contents("bot_data/action_$admin_id.txt", "wait_ban");
        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "📥 أرسل الآن آيدي (ID) الشخص الذي تريد حظره نهائياً من البوت:"
        ]);
    }
    if ($data == "unban_user") {
        file_put_contents("bot_data/action_$admin_id.txt", "wait_unban");
        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "📥 أرسل الآن آيدي (ID) الشخص الذي تريد إلغاء الحظر عنه:"
        ]);
    }
    exit;
}

// تنفيذ مدخلات الأدمن
if (isset($text) && $from_id == $admin_id && $action != "null") {
    if ($action == "wait_welcome") {
        file_put_contents("bot_data/welcome.txt", $text);
        file_put_contents("bot_data/action_$admin_id.txt", "null");
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ تم حفظ كليشة الترحيب الجديدة بنجاح!",
            'reply_to_message_id' => $message_id
        ]);
        exit;
    }
    if ($action == "wait_ban" && is_numeric($text)) {
        file_put_contents("bot_data/ban.txt", $text . "\n", FILE_APPEND);
        file_put_contents("bot_data/action_$admin_id.txt", "null");
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ تم حظر العضو صاحب الآيدي ($text) بنجاح.",
            'reply_to_message_id' => $message_id
        ]);
        exit;
    }
    if ($action == "wait_unban" && is_numeric($text)) {
        $str = file_get_contents("bot_data/ban.txt");
        $str = str_replace("$text\n", "", $str);
        file_put_contents("bot_data/ban.txt", $str);
        file_put_contents("bot_data/action_$admin_id.txt", "null");
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ تم إلغاء حظر العضو صاحب الآيدي ($text) بنجاح.",
            'reply_to_message_id' => $message_id
        ]);
        exit;
    }
}

// تفاعل البوت مع المستخدمين العاديين
if (isset($text)) {
    if ($text == "/start") {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $welcome_msg,
            'reply_to_message_id' => $message_id,
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => 'تابع جديدنا هنا ✅', 'url' => "https://t.me/G_J_RR"]]
                ]
            ])
        ]);
    } else {
        // مفعول الذكاء الاصطناعي عند إرسال أي نص
        bot('sendChatAction', [
            'chat_id' => $chat_id,
            'action' => 'typing'
        ]);
        
        $reply = ask_gemini($text, $gemini_api_key);
        
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $reply,
            'reply_to_message_id' => $message_id
        ]);
    }
}

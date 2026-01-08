<?php
/**
 * Bot Híbrido: IA para conversar + Respuestas Fijas para Planes/Pagos
 */

// ================== CARGAR VARIABLES DE ENTORNO ==================
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        return;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Ignorar líneas vacías y comentarios
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        // Verificar que tenga el formato KEY=VALUE
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        // Remover comillas si existen
        $value = trim($value, '"\'');
        // Solo establecer si no existe ya
        if (!empty($name) && !array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Cargar archivo .env si existe
loadEnv(__DIR__ . '/.env');

// ================== CONFIGURACIÓN ==================
$idInstance       = getenv('GREEN_ID') ?: '';
$apiTokenInstance = getenv('GREEN_TOKEN') ?: '';
$apiUrlBase       = getenv('GREEN_API_URL') ?: '';
$OPENAI_API_KEY   = getenv('OPENAI_API_KEY') ?: '';
$OPENAI_MODEL     = getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';
$VISION_MODEL     = getenv('VISION_MODEL') ?: 'gpt-4o-mini';

// Validar que las variables críticas estén configuradas
if (empty($idInstance) || empty($apiTokenInstance) || empty($apiUrlBase)) {
    error_log('ERROR: Variables de GreenAPI no configuradas. Verifica tu archivo .env');
}

// ================== DATOS DEL NEGOCIO ==================
$PLANS = [
  ['emoji'=>'🔥',  'name'=>'Netflix original (1 pantalla, 1 mes)', 'price'=>11000],
  ['emoji'=>'✨',  'name'=>'Disney Premium (1 mes)', 'price'=>9900],
  ['emoji'=>'❤‍🔥','name'=>'Netflix + Disney Premium (1 mes)', 'price'=>15900],
  ['emoji'=>'🎟',  'name'=>'Netflix + prime video (1 mes)', 'price'=>15900],
  ['emoji'=>'🫰',  'name'=>'Netflix + HBO Max (1 mes)', 'price'=>15900],
  ['emoji'=>'🤯',  'name'=>'Netflix + Crunchyroll (1 mes)', 'price'=>15900],
  ['emoji'=>'💥',  'name'=>'Netflix + Paramount (1 mes)', 'price'=>15900],
  ['emoji'=>'✅',  'name'=>'Netflix + hbo max+ Amazon (1 mes)', 'price'=>20900],
  ['emoji'=>'✅',  'name'=>'Netflix + disney premium+ HBO Max (1 mes)', 'price'=>21900],
  ['emoji'=>'✅',  'name'=>'Netflix + Disney Premium + Amazon + HBO Max (1 mes)', 'price'=>26900],
];

// Servicios individuales (para IA y combos personalizados)
$SERVICES = [
'netflix' => [
        'name' => 'Netflix (1 pantalla)',
        'price' => 11000,
        'keywords' => ['netflix','nflx','netflis','netfli','netlix','netfliix','netflics']
    ],
    'disney' => [
        'name' => 'Disney Premium',
        'price' => 9900,
        'keywords' => ['disney','disney+','disnei','dysney','disney plus','disneyplus']
    ],
    'amazon' => [
        'name' => 'Amazon Prime Video',
        'price' => 9000,
        'keywords' => ['amazon','prime video','prime','primevideo','amazon prime','primevideo','video prime']
    ],
    'hbo' => [
        'name' => 'HBO Max',
        'price' => 9000,
        'keywords' => ['hbo','hbo max','hbomax','max','hbo+','max hbo']
    ],
    'crunchyroll' => [
        'name' => 'Crunchyroll',
        'price' => 8000,
        'keywords' => ['crunchy','crunchyroll','crunchiroll','crunch','crounchy','crounchyroll']
    ],
    'paramount' => [
        'name' => 'Paramount+',
        'price' => 8000,
        'keywords' => ['paramount','paramount+','paramount plus','paramountplus','paramon','paramon+']
    ]
];

$PAYMENT_INFO = "💳 *MEDIOS DE PAGO*\n\n🩵 *NEQUI:* 3207702142 (Hernan Ceballos)\n🏦 *DAVIPLATA:* 3218474247 (Johan Rondon)\n🏦 *Ahorros Bancolombia:* 05900012119 (Johan Javier Rondon)\n\n📍 *Importante:* envía captura del pago por aquí";
$ALLOWED_ACCOUNTS = [
    ['method'=>'NEQUI','number'=>'3207702142'],
    ['method'=>'DAVIPLATA','number'=>'3218474247'],
    ['method'=>'BANCOLOMBIA','number'=>'05900012119'],
    ['method'=>'LLAVE_BREVE','gmail'=>'johanjavier654@gmail.com'],
];
$MAX_DAYS_SINCE_PAYMENT = (int) (getenv('MAX_DAYS_SIN_PAYMENT') ?: 1);
$ACCOUNT_HOLDERS = [
    'NEQUI' => 'Hernan Ceballos',
    'DAVIPLATA' => 'Johan Rondon',
    'BANCOLOMBIA' => 'Johan Javier Rondon',
    'LLAVE_BREVE' => 'Johanjavier654@gmail.com',
];
$DELIVERY_INFO = "✅ ¡Perfecto! Tu comprobante fue validado correctamente.\n\n📦 Para recibir tu servicio, escríbele a nuestro número de entregas:\n\n👉 WhatsApp: +57 324 493 0475\n🔗 O presiona aquí: https://wa.me/573244930475\n\n📋 Envíale:\n• La captura del pago\n• Tu nombre completo\n\n¡Gracias por tu compra! 🎉";

// Información de confianza para el negocio
$TRUST_INFO = [
    'city' => 'Colombia', // Cambia esto por tu ciudad
    'location' => 'Operamos desde Colombia',
    'guarantee' => 'Garantía de 30 días en todos nuestros servicios',
    'experience' => 'Años de experiencia en el mercado',
];

// Carpeta para historial simple de chat (contexto para la IA)
$HISTORY_DIR = __DIR__ . '/chat_memory';
if (!is_dir($HISTORY_DIR)) {
    @mkdir($HISTORY_DIR, 0777, true);
}
$PAYMENT_STATUS_FILE = __DIR__.'/data/payment_status.json';
if(!is_dir(__DIR__.'/data')) @mkdir(__DIR__.'/data',0777,true);

function historyPath($chatId){
    global $HISTORY_DIR;
    $safe = preg_replace('/[^a-z0-9_@\.-]/i','_', $chatId);
    return $HISTORY_DIR . "/{$safe}.json";
}

function loadChatHistory($chatId){
    $path = historyPath($chatId);
    if(!file_exists($path)) return [];
    $json = @file_get_contents($path);
    $data = json_decode($json,true);
    return is_array($data) ? $data : [];
}

function saveChatHistory($chatId,$history){
    $path = historyPath($chatId);
    $trimmed = count($history)>20 ? array_slice($history,-20) : $history;
    @file_put_contents($path, json_encode($trimmed, JSON_UNESCAPED_UNICODE));
}

function pushHistory(&$history,$role,$content){
    $content = trim((string)$content);
    if($content==='') return;
    $history[] = ['role'=>$role,'content'=>mb_substr($content,0,1000)];
    if(count($history)>20){
        $history = array_slice($history,-20);
    }
}

function sendAndRemember($chatId,$text,&$history){
    sendText($chatId,$text);
    pushHistory($history,'assistant',$text);
    saveChatHistory($chatId,$history);
}

function updatePaymentStatus($chatId,$status){
    global $PAYMENT_STATUS_FILE;
    $existing = [];
    if(file_exists($PAYMENT_STATUS_FILE)){
        $json = @file_get_contents($PAYMENT_STATUS_FILE);
        $decoded = json_decode($json,true);
        if(is_array($decoded)) $existing = $decoded;
    }
    $existing[$chatId] = ['status'=>$status,'updatedAt'=>date('c')];
    @file_put_contents($PAYMENT_STATUS_FILE,json_encode($existing,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

function getPaymentStatus($chatId){
    global $PAYMENT_STATUS_FILE;
    if(!file_exists($PAYMENT_STATUS_FILE)) return null;
    $json = @file_get_contents($PAYMENT_STATUS_FILE);
    $decoded = json_decode($json,true);
    if(!is_array($decoded)) return null;
    $entry = $decoded[$chatId] ?? null;
    if(!is_array($entry)) return null;
    return $entry['status'] ?? null;
}

function detectHolderQuery($textLower){
    if(preg_match('/a\s*(que|qué)\s*nombre/u', $textLower)) return true;
    if(preg_match('/nombre\s*(esta|est[aá])/u', $textLower)) return true;
    if(preg_match('/a\s*(que|qué)\s*cuenta/u', $textLower)) return true;
    if(preg_match('/cuenta\s*(esta|est[aá]|conectada)/u', $textLower)) return true;
    if(preg_match('/(que|qué)\s*banco/u', $textLower)) return true;
    return str_contains($textLower,'a nombre') ||
           str_contains($textLower,'titular') ||
           str_contains($textLower,'de quien') ||
           str_contains($textLower,'quien recibe') ||
           str_contains($textLower,'quien es el dueno') ||
           str_contains($textLower,'quien es el dueño') ||
           str_contains($textLower,'quien es el propietario') ||
           str_contains($textLower,'propietario') ||
           str_contains($textLower,'cuenta de banco') ||
           str_contains($textLower,'cuenta bancaria') ||
           str_contains($textLower,'conectada');
}

function detectTrustQuery($textLower){
    return str_contains($textLower,'ciudad') ||
           str_contains($textLower,'ubicacion') ||
           str_contains($textLower,'ubicación') ||
           str_contains($textLower,'donde estas') ||
           str_contains($textLower,'dónde estás') ||
           str_contains($textLower,'de donde') ||
           str_contains($textLower,'de dónde') ||
           str_contains($textLower,'tramposo') ||
           str_contains($textLower,'estafador') ||
           str_contains($textLower,'confianza') ||
           str_contains($textLower,'seguro') ||
           str_contains($textLower,'confiable') ||
           str_contains($textLower,'experiencia') ||
           (str_contains($textLower,'pasar') && str_contains($textLower,'experiencia'));
}

function detectTechnicalQuery($textLower){
    return str_contains($textLower,'pantalla') ||
           str_contains($textLower,'bloqueo') ||
           str_contains($textLower,'bloqueada') ||
           str_contains($textLower,'activacion') ||
           str_contains($textLower,'activación') ||
           str_contains($textLower,'error') ||
           str_contains($textLower,'problema') ||
           str_contains($textLower,'soporte') ||
           str_contains($textLower,'asistencia') ||
           str_contains($textLower,'tigo') ||
           str_contains($textLower,'claro') ||
           str_contains($textLower,'movistar') ||
           (str_contains($textLower,'atencion') && str_contains($textLower,'cliente')) ||
           (str_contains($textLower,'atención') && str_contains($textLower,'cliente'));
}

function detectScreenProblem($textLower){
    return (str_contains($textLower,'pantalla') && (str_contains($textLower,'problema') || str_contains($textLower,'error') || str_contains($textLower,'bloqueo') || str_contains($textLower,'bloqueada') || str_contains($textLower,'no funciona') || str_contains($textLower,'no sirve'))) ||
           (str_contains($textLower,'pantalla') && str_contains($textLower,'activacion')) ||
           (str_contains($textLower,'pantalla') && str_contains($textLower,'activación'));
}

function buildHolderMessage($textLower){
    global $ACCOUNT_HOLDERS;
    $parts=[];
    $all = true;
    if(str_contains($textLower,'nequi')){ $parts[]="El Nequi está a nombre de *{$ACCOUNT_HOLDERS['NEQUI']}*."; $all=false; }
    if(str_contains($textLower,'daviplata')){ $parts[]="Daviplata está a nombre de *{$ACCOUNT_HOLDERS['DAVIPLATA']}*."; $all=false; }
    if(str_contains($textLower,'bancolombia') || str_contains($textLower,'cuenta de ahorro') || str_contains($textLower,'ahorro')){ $parts[]="La cuenta Bancolombia está a nombre de *{$ACCOUNT_HOLDERS['BANCOLOMBIA']}*."; $all=false; }
    if($all){
        $parts[]="El Nequi está a nombre de *{$ACCOUNT_HOLDERS['NEQUI']}*.";
        $parts[]="Daviplata está a nombre de *{$ACCOUNT_HOLDERS['DAVIPLATA']}*.";
        $parts[]="La cuenta Bancolombia está a nombre de *{$ACCOUNT_HOLDERS['BANCOLOMBIA']}*.";
    }
    return implode("\n",$parts);
}

function sendSupportEscalation($chatId,&$history,$isPaymentReceipt=false){
    if($isPaymentReceipt){
        // Si es un comprobante pero no se validó, redirigir al WhatsApp de entregas para validación manual
        sleep(rand(2, 3)); // Pausa natural
        $manualValidationMsg = "⚠️ No pude validar automáticamente tu comprobante.\n\n" .
                               "Para validarlo manualmente, escríbele directamente a nuestro número de entregas:\n\n" .
                               "📱 WhatsApp: +57 324 493 0475\n" .
                               "🔗 O presiona aquí: https://wa.me/573244930475\n\n" .
                               "📋 Envíale:\n" .
                               "• La captura del comprobante\n" .
                               "• Tu nombre completo\n\n" .
                               "Ellos te ayudarán a validar el pago y activar tu servicio. 💪";
        sendAndRemember($chatId,$manualValidationMsg,$history);
        updatePaymentStatus($chatId,'yellow');
    } else {
        // Si no es un comprobante, mantener el mensaje actual
        sendAndRemember($chatId,"🟡 No pude validar esa imagen. Vuelve a enviarla cuando tengas el comprobante completo.",$history);
        updatePaymentStatus($chatId,'yellow');
    }
}

function assistantAskedPaymentsRecently($history){
    for($i=count($history)-1; $i>=0; $i--){
        if(($history[$i]['role'] ?? '') === 'assistant'){
            $text = mb_strtolower($history[$i]['content'] ?? '');
            if(str_contains($text,'medios de pago') || str_contains($text,'datos de pago')){
                return true;
            }
            break;
        }
    }
    return false;
}

function assistantSentPaymentInfo($history){
    foreach(array_reverse($history) as $entry){
        if(($entry['role'] ?? '') === 'assistant'){
            $text = mb_strtolower($entry['content'] ?? '');
            if(str_contains($text,'nequi') || str_contains($text,'daviplata') || str_contains($text,'ahorros bancolombia')){
                return true;
            }
        }
    }
    return false;
}

function assistantSentPlans($history){
    foreach(array_reverse($history) as $entry){
        if(($entry['role'] ?? '') === 'assistant'){
            $text = mb_strtolower($entry['content'] ?? '');
            if(str_contains($text,'combos disponibles') || str_contains($text,'planes disponibles') || str_contains($text,'¿cuál te interesa?') || str_contains($text,'cual te interesa?')){
                return true;
            }
        }
    }
    return false;
}

function wantsPaymentDetails($textLower){
    $verbs = '(pasame|p[aá]same|env[ií]ame|manda(me)?|mandame|dame|reg[aá]lame|facil[ií]tame|podr[ií]as|podr[ií]as pasar|podes pasar|me puedes|me podr[ií]as|me das|me indicas|me dices|comp[aá]rteme|enviame|mu[eé]strame|pasa|p[aá]salo|p[aá]salo)';
    if(preg_match('/'.$verbs.'.*(medios|datos|cuenta|n[uú]mero|numero|nequi|daviplata|bancolombia)/u', $textLower)) return true;
    if(preg_match('/(medios?|datos?) de (pago|nequi|daviplata|bancolombia)/u', $textLower)) return true;
    if(preg_match('/(cu[aá]l(es)? (es|son)|cuentame|cu[ée]ntame).*(medios|datos|cuenta|numero|n[uú]mero|nequi|daviplata|bancolombia)/u', $textLower)) return true;
    if(preg_match('/(n[uú]mero|numero) (de )?(cuenta|nequi|daviplata|bancolombia)/u', $textLower)) return true;
    if(str_contains($textLower,'medios de pago') || str_contains($textLower,'datos de pago')) return true;
    return false;
}

function detectServicesFromText($textLower, $SERVICES){
    $textNormalized = normalizeServiceText($textLower);
    $found = [];
    foreach($SERVICES as $slug => $info){
        foreach($info['keywords'] as $kw){
            $kwLower = normalizeServiceText($kw);
            if($kwLower === '') continue;
            if(str_contains($textNormalized, $kwLower)){
                $found[$slug] = true;
                break;
            }
        }
    }
    return array_keys($found);
}

function buildPlanServiceSets($PLANS,$SERVICES){
    $sets = [];
    foreach($PLANS as $plan){
        $text = mb_strtolower($plan['name'].' '.$plan['emoji'], 'UTF-8');
        $services = detectServicesFromText($text,$SERVICES);
        if($services){
            sort($services);
            $sets[] = $services;
        }
    }
    return $sets;
}

function matchesExistingPlanCombo($services,$planServiceSets){
    $temp = $services;
    sort($temp);
    foreach($planServiceSets as $set){
        if($set === $temp) return true;
    }
    return false;
}

function detectAdHocComboRequest($textLower,$SERVICES,$planServiceSets){
    $services = detectServicesFromText($textLower,$SERVICES);
    if(count($services)<2) return null;
    if(matchesExistingPlanCombo($services,$planServiceSets)) return null;
    $names = [];
    $total = 0;
    $includesNetflix = in_array('netflix',$services,true);
    foreach($services as $slug){
        if(!isset($SERVICES[$slug])) continue;
        $info = $SERVICES[$slug];
        $total += (int)$info['price'];
        $names[] = $info['name'];
    }
    if(count($names)<2) return null;
    $discountPercent = $includesNetflix ? 0 : 30;
    $discountAmount = $discountPercent ? round($total * $discountPercent / 100) : 0;
    $final = $total - $discountAmount;
    return [
        'services'=>$names,
        'serviceSlugs'=>$services,
        'total'=>$total,
        'discountPercent'=>$discountPercent,
        'final'=>$final
    ];
}

$PLAN_SERVICE_SETS = buildPlanServiceSets($PLANS,$SERVICES);

// ================== FUNCIONES HELPER (GreenAPI) ==================
function sendText($chatId, $text) {
    global $idInstance, $apiTokenInstance, $apiUrlBase;
    $url = rtrim($apiUrlBase, '/') . "/waInstance{$idInstance}/SendMessage/{$apiTokenInstance}";
    
    $data = [
        'chatId' => $chatId,
        'message' => $text
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    curl_exec($ch);
    curl_close($ch);
}

function getPlansText($planes) {
    $txt = "🎬 *PLANES DE STREAMING DISPONIBLES*\n\n";
    foreach ($planes as $p) {
        $price = '$' . number_format($p['price'], 0, ',', '.');
        $txt .= "{$p['emoji']} *{$p['name']}*\n💰 {$price}\n\n";
    }
    $txt .= "✨ *Garantía de 30 días* en todos los planes\n";
    $txt .= "🚀 *Activación inmediata* después del pago\n";
    $txt .= "💳 *Múltiples medios de pago* disponibles\n";
    $txt .= "📱 *Soporte técnico* incluido\n\n";
    $txt .= "¿Cuál te interesa? 👇";
    return $txt;
}

function formatCop($value){
    return '$' . number_format((int)$value, 0, ',', '.');
}

function normalizeServiceText($text){
    $lower = mb_strtolower($text ?? '', 'UTF-8');
    $normalized = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$lower);
    return $normalized !== false ? $normalized : $lower;
}

function normalizeNameSimple($text){
    $lower = mb_strtolower($text ?? '', 'UTF-8');
    $normalized = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$lower);
    $normalized = $normalized !== false ? $normalized : $lower;
    return trim(preg_replace('/\s+/',' ', $normalized));
}

function matchAccountByHolder($holderNorm){
    global $ALLOWED_ACCOUNTS,$ACCOUNT_HOLDERS;
    if($holderNorm==='') return null;
    foreach($ALLOWED_ACCOUNTS as $acc){
        $expected = normalizeNameSimple($ACCOUNT_HOLDERS[$acc['method']] ?? '');
        if($expected==='') continue;
        $expTokens = array_filter(explode(' ', $expected));
        $matchedTokens = 0;
        foreach($expTokens as $token){
            if($token !== '' && str_contains($holderNorm,$token)) $matchedTokens++;
        }
        if($matchedTokens >= max(1,count($expTokens)-1)){
            return $acc;
        }
    }
    return null;
}

function onlyDigits($value){
    return preg_replace('/\D+/','', (string)$value);
}

function downloadAsBase64($url){
    if(!$url) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $bin = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if($err || !$bin) return null;
    return base64_encode($bin);
}

function extractPaymentFromImage($b64,$mime){
    global $OPENAI_API_KEY,$VISION_MODEL;
    if(!$OPENAI_API_KEY || !$b64) return null;
    $dataUrl = "data:{$mime};base64,{$b64}";
    $payload = [
        'model' => $VISION_MODEL,
        'temperature' => 0,
        'response_format' => ['type'=>'json_object'],
        'messages' => [
            ['role'=>'system','content'=>'Eres un verificador experto de comprobantes. Responde solo JSON con este formato: {"is_payment":true/false,"account_number":"string","amount":"string","date":"string","holder_name":"string","notes":"string"}.'],
            ['role'=>'user','content'=>[
                ['type'=>'text','text'=>"Analiza la imagen y dime si es un comprobante de pago real. Si no lo es, marca is_payment=false. Extrae número de cuenta destino (solo dígitos), valor enviado (amount), fecha y nombre del titular destino (holder_name)."],
                ['type'=>'image_url','image_url'=>['url'=>$dataUrl]]
            ]]
        ]
    ];
    $ch=curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>[
            'Content-Type: application/json',
            'Authorization: Bearer '.$OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>45
    ]);
    $resp=curl_exec($ch); $err=curl_error($ch); curl_close($ch);
    if($err || !$resp) return null;
    $obj=json_decode($resp,true);
    $content=$obj['choices'][0]['message']['content'] ?? '';
    $data=json_decode($content,true);
    return is_array($data)?$data:null;
}

function parseDateOnly($s){
    $s=trim((string)$s);
    if($s==='') return null;
    $lower = mb_strtolower($s,'UTF-8');
    $ascii = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$lower);
    $text = $ascii !== false ? $ascii : $lower;
    // quitar hora o texto posterior
    $text = preg_replace('/\-\s*\d{1,2}[:.]\d{2}.*$/','',$text);
    $parts = preg_split('/( a las | hora )/',$text);
    $text = trim($parts[0] ?? $text);
    $months = [
        'enero'=>1,'ene'=>1,'january'=>1,'jan'=>1,
        'febrero'=>2,'feb'=>2,'february'=>2,
        'marzo'=>3,'mar'=>3,'march'=>3,
        'abril'=>4,'abr'=>4,'april'=>4,'apr'=>4,
        'mayo'=>5,'may'=>5,
        'junio'=>6,'jun'=>6,'june'=>6,
        'julio'=>7,'jul'=>7,'july'=>7,
        'agosto'=>8,'ago'=>8,'august'=>8,'aug'=>8,
        'septiembre'=>9,'setiembre'=>9,'sept'=>9,'set'=>9,'sep'=>9,'september'=>9,
        'octubre'=>10,'oct'=>10,'october'=>10,
        'noviembre'=>11,'nov'=>11,'november'=>11,
        'diciembre'=>12,'dic'=>12,'december'=>12,'dec'=>12
    ];
    $monthPattern = implode('|', array_map(fn($k)=>preg_quote($k,'/'), array_keys($months)));
    if(preg_match('/(\d{1,2})\s*(de)?\s*('.$monthPattern.')\s*(de)?\s*(\d{2,4})/u',$text,$m)){
        $d=(int)$m[1];
        $monthKey=$m[3];
        $month = $months[$monthKey] ?? null;
        if(!$month) return null;
        $y=(int)$m[5];
        if($y<100) $y += 2000;
        $dt = DateTime::createFromFormat('!Y-m-d', sprintf('%04d-%02d-%02d',$y,$month,$d));
        if($dt) return $dt;
    }
    if(preg_match('/('.$monthPattern.')\s*(\d{1,2})\s*(de)?\s*(\d{2,4})/u',$text,$m)){
        $monthKey=$m[1];
        $month = $months[$monthKey] ?? null;
        $d=(int)$m[2];
        $y=(int)$m[4];
        if($y<100) $y += 2000;
        $dt = DateTime::createFromFormat('!Y-m-d', sprintf('%04d-%02d-%02d',$y,$month,$d));
        if($dt) return $dt;
    }
    if(preg_match('/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/',$text,$m)){
        $dt = DateTime::createFromFormat('!Y-m-d', sprintf('%04d-%02d-%02d',$m[1],$m[2],$m[3]));
        if($dt) return $dt;
    }
    if(preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/',$text,$m)){
        $y=(int)$m[3]; if($y<100) $y+=2000;
        $dt = DateTime::createFromFormat('!Y-m-d', sprintf('%04d-%02d-%02d',$y,$m[2],$m[1]));
        if($dt) return $dt;
    }
    if(!preg_match_all('/\d{1,4}/',$text,$numbers) || count($numbers[0])<3) return null;
    $nums=$numbers[0];
    // buscar patrón dia mes año
    for($i=0;$i<=count($nums)-3;$i++){
        $a=(int)$nums[$i];
        $b=(int)$nums[$i+1];
        $c=(int)$nums[$i+2];
        if($a>=1 && $a<=31 && $b>=1 && $b<=12 && $c>=2000){
            $dt = DateTime::createFromFormat('!Y-m-d', sprintf('%04d-%02d-%02d',$c,$b,$a));
            if($dt) return $dt;
        }
        if($a>=2000 && $b>=1 && $b<=12 && $c>=1 && $c<=31){
            $dt = DateTime::createFromFormat('!Y-m-d', sprintf('%04d-%02d-%02d',$a,$b,$c));
            if($dt) return $dt;
        }
    }
    return null;
}

function accountMatchesAllowed($detected,$allowed){
    $det = onlyDigits($detected);
    $all = onlyDigits($allowed);
    if($det==='' || $all==='') return false;
    if($det === $all) return true;
    if(strlen($det)>=8 && substr($all,-8) === substr($det,-8)) return true;
    if(strlen($det)<=4 && substr($all,-4) === $det) return true;
    return false;
}

function daviplataNameLooksOk($holder){
    $h = normalizeNameSimple($holder);
    if($h==='') return false;
    $hasJohan = str_contains($h,'johan');
    $hasRondon = str_contains($h,'rondon');
    return $hasJohan && $hasRondon;
}

function validatePaymentByAccountAndDate($extracted,$ALLOWED_ACCOUNTS,$MAX_DAYS_SINCE_PAYMENT){
    $reasons=[]; $matched=null;
    $acc=onlyDigits($extracted['account_number'] ?? '');
    $holderNorm = normalizeNameSimple($extracted['holder_name'] ?? '');
    if($acc!==''){
        foreach((array)$ALLOWED_ACCOUNTS as $a){
            $allowed = onlyDigits($a['number'] ?? '');
            if($allowed==='') continue;
            if(accountMatchesAllowed($acc,$allowed)){ $matched=$a; break; }
        }
        if(!$matched && $holderNorm!==''){
            $matched = matchAccountByHolder($holderNorm);
        }
        if(!$matched){
            $reasons[]='La *cuenta destino* no coincide con nuestras cuentas.';
        }
    } else {
        if($holderNorm!==''){
            $matched = matchAccountByHolder($holderNorm);
        }
        if(!$matched){
            $reasons[]='No se detectó *número de cuenta destino* en la imagen.';
        }
    }
    if($matched && strtoupper($matched['method'])==='DAVIPLATA'){
        $holder = $extracted['holder_name'] ?? '';
        if(!daviplataNameLooksOk($holder)){
            $reasons[]='El *titular Daviplata* no coincide con "Johan Rondon".';
        }
    }
    $skipDateCheck = $matched && strtoupper($matched['method']) === 'BANCOLOMBIA';
    if(!$skipDateCheck){
        $dateStr=$extracted['date'] ?? '';
        if(!$dateStr){
            $reasons[]='No se encontró la *fecha* del pago.';
        } else {
            $d=parseDateOnly($dateStr);
            if(!$d){
                $reasons[]='No pude interpretar la *fecha* del comprobante.';
            } else {
                $today=new DateTime('today');
                $diff=abs($today->getTimestamp()-$d->getTimestamp())/86400;
                if($diff>$MAX_DAYS_SINCE_PAYMENT){
                    $reasons[]='La *fecha* del comprobante parece antigua.';
                }
            }
        }
    }
    return ['ok'=>count($reasons)===0,'reasons'=>$reasons,'matched'=>$matched];
}

function handlePaymentCapture($chatId,$messageData,&$history){
    global $ALLOWED_ACCOUNTS,$MAX_DAYS_SINCE_PAYMENT,$DELIVERY_INFO,$ACCOUNT_HOLDERS;
    if(empty($messageData['fileMessageData'])) return false;
    $downloadUrl = $messageData['fileMessageData']['downloadUrl'] ?? null;
    if(!$downloadUrl) return false;
    $mime = $messageData['fileMessageData']['mimeType'] ?? 'image/jpeg';
    pushHistory($history,'user','[imagen]');
    saveChatHistory($chatId,$history);
    sendAndRemember($chatId,"Recibí tu imagen, dame un momento para validarla 🙌",$history);
    $b64 = downloadAsBase64($downloadUrl);
    if(!$b64){
        sendAndRemember($chatId,"No pude descargar la imagen. ¿Podrías reenviarla en buena calidad, por favor?",$history);
        sendSupportEscalation($chatId,$history);
        return true;
    }
    $analysis = extractPaymentFromImage($b64,$mime);
    if(!$analysis){
        sendAndRemember($chatId,"Esa imagen no parece un comprobante. ¿Puedes enviarme la captura del recibo donde se vea el banco, la fecha y la cuenta destino, porfa?",$history);
        sendSupportEscalation($chatId,$history,false); // No es un comprobante
        return true;
    }
    if(empty($analysis['is_payment'])){
        sendAndRemember($chatId,"Parece que la imagen no es un comprobante de pago. Necesito la captura completa del recibo para continuar.",$history);
        sendSupportEscalation($chatId,$history,false); // No es un comprobante
        return true;
    }
    // Si llegamos aquí, es un comprobante real (is_payment = true)
    $validation = validatePaymentByAccountAndDate($analysis,$ALLOWED_ACCOUNTS,$MAX_DAYS_SINCE_PAYMENT);
    if(!$validation['ok']){
        // Es un comprobante pero no se validó, redirigir al WhatsApp de entregas
        sendSupportEscalation($chatId,$history,true); // Es un comprobante, redirigir
        return true;
    }
    $matched = $validation['matched'];
    $expectedHolder = $ACCOUNT_HOLDERS[$matched['method']] ?? '';
    $detectedHolder = trim((string)($analysis['holder_name'] ?? ''));
    if($expectedHolder){
        if($detectedHolder===''){
            // Es un comprobante pero falta información, redirigir al WhatsApp
            sendSupportEscalation($chatId,$history,true);
            return true;
        }
        $expNorm = normalizeNameSimple($expectedHolder);
        $detNorm = normalizeNameSimple($detectedHolder);
        $expTokens = array_filter(explode(' ', $expNorm));
        $okTokens = 0;
        foreach($expTokens as $token){
            if($token !== '' && str_contains($detNorm,$token)) $okTokens++;
        }
        if($okTokens < max(1,count($expTokens)-1)){
            // Es un comprobante pero el titular no coincide, redirigir al WhatsApp
            sendSupportEscalation($chatId,$history,true);
            return true;
        }
    }
    $amount = trim((string)($analysis['amount'] ?? ''));
    if($amount===''){
        // Es un comprobante pero falta el monto, redirigir al WhatsApp
        sendSupportEscalation($chatId,$history,true);
        return true;
    }
    $date = trim((string)($analysis['date'] ?? ''));
    if($date===''){
        // Es un comprobante pero falta la fecha, redirigir al WhatsApp
        sendSupportEscalation($chatId,$history,true);
        return true;
    }
    sendAndRemember($chatId,$DELIVERY_INFO,$history);
    updatePaymentStatus($chatId,'green');
    return true;
}

    // ================== FUNCIÓN IA (OpenAI) ==================
function getAIResponse($userMessage, $contextPlans, $contextIndividuals, $chatHistory = []) {
    global $OPENAI_API_KEY, $OPENAI_MODEL, $PAYMENT_INFO;

    if (!$OPENAI_API_KEY) {
        return "Hola, en este momento estoy en mantenimiento. Por favor escríbeme más tarde.";
    }

    // Limpiamos los datos de pago para el prompt (sin asteriscos excesivos para que la IA los lea bien)
    $paymentPrompt = str_replace('*', '', $PAYMENT_INFO);

    $systemPrompt = "Eres Javier, un asesor de ventas colombiano que vende cuentas de streaming.
    
    TUS PRODUCTOS Y PRECIOS UNITARIOS:
    {$contextIndividuals}

    COMBOS YA ARMADOS (Precios Fijos):
    {$contextPlans}

    TUS DATOS DE PAGO:
    {$paymentPrompt}
    
    REGLAS MATEMÁTICAS:
    1. Multiplica precio x cantidad si piden varios meses/pantallas.
    2. Combos personalizados (mezclas raras): Suma precios unitarios.
       - Si incluye NETFLIX: Precio FULL (sin descuento).
       - Si NO incluye Netflix: Aplica 30% de DESCUENTO.
    
    OBJETIVO:
    1. Responder cualquier pregunta que haga el cliente, sin evadirla.
    2. Mantener las respuestas CORTAS (1 a 3 frases). Máximo 2–3 líneas en WhatsApp.
    3. Cuando sea natural, cerrar con una pregunta de venta (plan / medios de pago), sin cambiar de tema bruscamente.
    
    GENERAR CONFIANZA Y AYUDAR (IMPORTANTE):
    - Si preguntan sobre CIUDAD o UBICACIÓN: Responde con confianza. Ejemplo: 'Operamos desde Colombia. Tenemos años de experiencia y garantía de 30 días en todos nuestros servicios. ¿Qué plan te interesa?'
    - Si tienen PREOCUPACIONES sobre estafadores o seguridad: Muestra EMPATÍA y da información que genere confianza. Ejemplo: 'Entiendo tu preocupación, es normal ser cuidadoso. Operamos desde Colombia, tenemos garantía de 30 días y puedes verificar nuestros datos de pago. ¿Te muestro los planes disponibles?'
    - Si preguntan sobre GARANTÍAS o SEGURIDAD: Explica brevemente la garantía de 30 días y luego ofrece planes.
    - Si preguntan sobre PROBLEMAS TÉCNICOS relacionados con streaming (pantallas bloqueadas, activación, errores, asistencia al cliente, Tigo, Claro): Responde de manera ÚTIL, DIRECTA y EMPÁTICA. Si el cliente pregunta sobre activación de pantallas o problemas técnicos, explícale brevemente que puede contactar el soporte del servicio (Tigo, Claro, etc.) y que con nuestros planes tiene garantía de 30 días. Sé EMPÁTICO pero CONCISO. Luego pregunta si necesita ayuda con algún plan o si ya tiene uno activo. NO uses respuestas genéricas largas.
    - Si preguntan sobre SOPORTE o ASISTENCIA: Ofrece ayuda básica y menciona la garantía, luego ofrece planes.
    - NUNCA ignores preocupaciones legítimas del cliente. Responde con empatía y da información útil antes de ofrecer planes.
    
    ESTILO Y EMPATÍA:
    1. Sé EMPÁTICO: Si el cliente tiene dudas o preocupaciones legítimas (seguridad, ubicación, estafadores), muéstrate comprensivo y da información que genere confianza.
    2. Sé NATURAL: Habla como colombiano pero mantén el foco en ventas.
    3. Sé ÚTIL: Responde preguntas sobre streaming, precios, pagos, garantías, y también sobre confianza/seguridad cuando sea relevante.
    4. Sé PACIENTE: Si no entiende algo sobre planes/pagos, explícaselo claramente.
    5. Sé POSITIVO: Mantén tono amigable y genera confianza antes de cerrar ventas.
    
    REGLAS DE RESPUESTA (CORTAS Y CLARAS):
    1. Responde la pregunta del cliente primero (aunque sea de otro tema).
    2. Evita textos largos, listas largas o explicaciones extensas. Si hace falta, haz 1 pregunta de aclaración.
    3. Si el tema es de streaming/planes: da la respuesta y ofrece el siguiente paso (plan o medios de pago).
    4. Si el tema NO es de streaming: responde breve y luego pregunta algo suave como: '¿Qué plan te interesa?' o '¿Te muestro los planes?'
    5. Si das un precio, cierra con: '¿Te paso medios de pago?' (puedes variarlo sin perder la intención).
    6. Si el usuario confirma pago o pide datos, ENVÍA LOS DATOS DE PAGO (arriba) y pide el comprobante.
    7. Si preguntan por titulares de pago, responde con: nequi(Hernan Ceballos), daviplata(Johan Rondon), bancolombia(Johan Javier Rondon). No inventes otros.
    
    MANEJO DE PREGUNTAS Y CONVERSACIONES:
    - PREGUNTAS DE CONFIANZA (ciudad, ubicación, estafadores, seguridad): Responde con EMPATÍA y da información que genere confianza. Ejemplo: 'Entiendo tu preocupación. Operamos desde Colombia, tenemos garantía de 30 días y años de experiencia. ¿Te muestro los planes disponibles?'
    - PREGUNTAS TÉCNICAS DE STREAMING (pantallas bloqueadas, activación, errores, soporte, asistencia al cliente): Responde con EMPATÍA y ofrece ayuda básica. Menciona la garantía de 30 días y luego ofrece planes. NO ignores estas preguntas, son legítimas.
    - PREGUNTAS SOBRE GARANTÍAS: Explica brevemente la garantía de 30 días y luego ofrece planes.
    - PREGUNTAS SOBRE STREAMING (planes, precios, servicios): Responde claramente y ofrece planes.
    - CONVERSACIONES CASUALES (expresiones colombianas, chistes, temas generales NO relacionados): Responde MUY BREVEMENTE (1-2 líneas máximo) y redirige: 'Jaja, pero mejor hablemos de tus planes. ¿Cuál te interesa?'
    - FRUSTRACIÓN O MOLESTIA: Muestra empatía y ofrece soluciones concretas relacionadas con ventas.
    
    RECUERDA: Eres un VENDEDOR que genera CONFIANZA. Responde con empatía a preocupaciones legítimas del cliente antes de cerrar la venta.
    ";

    $messages = [
        'model' => $OPENAI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
        ],
        'max_tokens' => 220, // Respuestas cortas
        'temperature' => 0.7 // Más naturalidad sin volverse largo
    ];

    $recentHistory = array_slice($chatHistory, -8);
    foreach($recentHistory as $entry){
        $role = ($entry['role'] === 'assistant') ? 'assistant' : 'user';
        $messages['messages'][] = [
            'role' => $role,
            'content' => $entry['content']
        ];
    }

    // Aseguramos que el último mensaje del usuario esté presente
    if(empty($recentHistory) || ($recentHistory[count($recentHistory)-1]['role'] ?? '') !== 'user'){
        $messages['messages'][] = ['role'=>'user','content'=>$userMessage];
    }

    $payload = $messages;

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $OPENAI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) return "Lo siento, tuve un pequeño error. ¿Me repites?";

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? "Dame un momento.";
}

// ================== LOGICA PRINCIPAL (Webhook) ==================

// Respuesta rápida a GET (verificación de GreenAPI)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo "Webhook activo";
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) { echo "No data"; exit; }

// Verificar si es un mensaje entrante
if (isset($data['typeWebhook']) && $data['typeWebhook'] === 'incomingMessageReceived') {
    
    $senderData = $data['senderData'];
    $chatId = $senderData['chatId'];
    
    // Ignorar grupos
    if (strpos($chatId, '@g.us') !== false) exit; 

    // Obtener texto
    $messageData = $data['messageData'];
    $textMessage = '';
    $typeMessage = $messageData['typeMessage'] ?? '';

    if (isset($messageData['textMessageData']['textMessage'])) {
        $textMessage = $messageData['textMessageData']['textMessage'];
    } elseif (isset($messageData['extendedTextMessageData']['text'])) {
        $textMessage = $messageData['extendedTextMessageData']['text'];
    }

    // Historial del chat para contexto
    $history = loadChatHistory($chatId);

    if($typeMessage === 'audioMessage' || $typeMessage === 'ptt'){
        sendAndRemember($chatId,"No puedo escuchar notas de voz 🙏. Escríbeme el mensaje y te respondo al toque.",$history);
        return;
    }
    $hasFile = isset($messageData['fileMessageData']);
    if($hasFile){
        $mime = strtolower($messageData['fileMessageData']['mimeType'] ?? '');
        if($typeMessage === 'stickerMessage' || str_contains($mime,'webp')){
            sendAndRemember($chatId,"No puedo procesar stickers 😅. Si necesitas algo, escríbemelo en texto.",$history);
            return;
        }
        if(handlePaymentCapture($chatId,$messageData,$history)){
            return;
        }
    }

    if (trim($textMessage)==='') {
        exit;
    }

    $textLower = mb_strtolower($textMessage);

    pushHistory($history,'user',$textMessage);
    saveChatHistory($chatId,$history);

    if(detectHolderQuery($textLower)){
        sleep(rand(2, 4)); // Pausa natural antes de responder
        $holderMsg = buildHolderMessage($textLower);
        sendAndRemember($chatId,$holderMsg,$history);
        return;
    }

    // Detectar preguntas sobre confianza/ubicación
    if(detectTrustQuery($textLower)){
        sleep(rand(2, 4)); // Pausa natural antes de responder
        global $TRUST_INFO;
        $trustMsg = "Entiendo perfectamente tu preocupación, es normal ser cuidadoso después de malas experiencias. " .
                   "Operamos desde {$TRUST_INFO['city']} y tenemos {$TRUST_INFO['experience']}. " .
                   "Ofrecemos {$TRUST_INFO['guarantee']}. " .
                   "Puedes verificar todos nuestros datos de pago y los titulares de las cuentas para tu tranquilidad. " .
                   "¿Te muestro los planes disponibles?";
        sendAndRemember($chatId, $trustMsg, $history);
        return;
    }

    // Detectar problemas específicos de pantalla y redirigir al WhatsApp de entregas
    if(detectScreenProblem($textLower)){
        sleep(rand(2, 3)); // Pausa natural antes de responder
        $payStatus = getPaymentStatus($chatId);
        if($payStatus === 'green'){
            $screenProblemMsg = "Entiendo tu problema con la pantalla ✅\n\n" .
                               "Como tu pago ya está confirmado, escríbele a nuestro WhatsApp de soporte/entregas para que te lo solucionen rápido:\n\n" .
                               "📱 WhatsApp: +57 324 493 0475\n" .
                               "🔗 https://wa.me/573244930475\n\n" .
                               "Envíales:\n" .
                               "• Tu nombre\n" .
                               "• Qué servicio tienes\n" .
                               "• Qué error te sale / qué pasa con la pantalla\n\n" .
                               "Allá te ayudan con activación, desbloqueo y cualquier falla 💪";
            sendAndRemember($chatId, $screenProblemMsg, $history);
        } elseif($payStatus === 'yellow'){
            // Pago pendiente / no validado: mandar a validación manual con el comprobante
            sendSupportEscalation($chatId, $history, true);
        } else {
            // Sin pago confirmado: no mandarlo a entregas; pedir comprobante o guiar a compra
            $screenPrePayMsg = "Te entiendo 🙌\n\n" .
                               "Para pasarte con soporte/entregas necesito tener el pago confirmado.\n" .
                               "Si *ya pagaste*, envíame el comprobante por aquí para validarlo.\n" .
                               "Si *aún no has pagado*, dime qué plan quieres y te paso los medios de pago.";
            sendAndRemember($chatId, $screenPrePayMsg, $history);
        }
        return;
    }

    // ================== 1. REGLAS FIJAS (Planes y Pagos) ==================
    
    // Detectar intención de ver PLANES (catálogo general) o MÁS INFO
    // REGLA AJUSTADA: Solo enviar menú si piden explícitamente planes/info, 
    // O si es un saludo MUY CORTO (inicio de conversación). Si es un saludo largo, que lo maneje la IA.
    
    $isGreeting = preg_match('/^\s*[¡!()]?\s*(hola|buenas|buenos dias|buenas tardes|buenas noches|hi|hello|oli|olis)\b/i', $textMessage);
    $isAskingPlans = preg_match('/(precios?|planes?|catalogo|valor|costo|cuesta|que vendes|tienes netflix|info|informacion|mas info|detalles|me interesa)/i', $textLower);
    
    $plansAlreadySent = assistantSentPlans($history);
    // Solo entrar aquí si piden planes, o si es saludo Y el mensaje es corto (menos de 20 caracteres)
    if ($isAskingPlans || ($isGreeting && strlen($textMessage) < 20)) {
        if($isAskingPlans || !$plansAlreadySent){
            // Solo saludar si el mensaje original era un saludo
            $prefix = "";
            if ($isGreeting) {
                $prefix = "¡Hola! 👋 Soy tu asesor de streaming. Mira nuestros combos disponibles:\n\n";
            }
            
            $plansText = getPlansText($PLANS);
            $hour = (int)date('G');
            if($hour >= 5 && $hour < 12){
                $saludo = "¡Buen día! 👋 Soy Javier, aquí para ayudarte con tus planes de streaming. Mira lo que tenemos disponible 👇\n\n";
            } elseif($hour >= 12 && $hour < 18){
                $saludo = "¡Buenas tardes! 👋 Soy Javier, aquí para ayudarte con tus planes de streaming. Mira lo que tenemos disponible 👇\n\n";
            } else {
                $saludo = "¡Buenas noches! 👋 Soy Javier, aquí para ayudarte con tus planes de streaming. Mira lo que tenemos disponible 👇\n\n";
            }
            $prefix = $saludo;
            sleep(rand(2, 3)); // Pausa antes de enviar planes
            sendAndRemember($chatId, $prefix, $history);
            sleep(1); // Pausa entre mensajes
            $plansText = getPlansText($PLANS);
            sendAndRemember($chatId, str_replace("🔹 *COMBOS DISPONIBLES*\n\n", "", $plansText), $history); 
        } else {
            sleep(rand(2, 3)); // Pausa natural
            sendAndRemember($chatId, "¡Hola de nuevo! Ya te compartí los planes hace un momento. Dime si quieres que te los reenvíe o si te aparto alguno.", $history);
        }
        exit;
    }

    // Detectar EMOJIS de planes específicos
    foreach ($PLANS as $plan) {
        // Si el mensaje contiene el emoji del plan
        if (strpos($textMessage, $plan['emoji']) !== false) {
            sleep(rand(2, 4)); // Pausa antes de confirmar plan
            $msg = "Excelente elección: {$plan['emoji']} *{$plan['name']}* por $" . number_format($plan['price'], 0, ',', '.') . ".\n\n¿Te paso los medios de pago?";
            sendAndRemember($chatId, $msg, $history);
            exit;
        }
    }

    // Detectar combos personalizados (ej: Paramount + Prime Video)
    $adHocCombo = detectAdHocComboRequest($textLower,$SERVICES,$PLAN_SERVICE_SETS);
    if($adHocCombo){
        $comboName = implode(' + ', $adHocCombo['services']);
        $final = formatCop($adHocCombo['final']);
        sleep(rand(3, 5)); // Pausa antes de calcular combo personalizado
        $msg = $adHocCombo['discountPercent'] > 0
            ? "Perfecto, {$comboName} queda en {$final} (precio final con 30% de descuento). ¿Te paso los medios de pago?"
            : "Perfecto, {$comboName} queda en {$final}. ¿Te paso los medios de pago?";
        sendAndRemember($chatId, $msg, $history);
        exit;
    }

    // Detectar solicitud explícita de medios o confirmación después de ofrecerlos
    $trimmedLower = trim($textLower);
    $explicitPaymentRequest = wantsPaymentDetails($textLower);
    $shortConfirmation = preg_match('/^(si|sii|siii|dale|claro|de una|envialos|pasalos|mandame|listo|vale|h[aá]gale|hagale)$/u', $trimmedLower);
    $askedBefore = assistantAskedPaymentsRecently($history);
    $requestedResend = preg_match('/(reenv[ií]a|manda de nuevo|otra vez|nuevamente|repite).*(medios|datos|cuenta|n[uú]mero|numero)/u', $textLower);
    $paymentsAlreadySent = assistantSentPaymentInfo($history);

    if ($explicitPaymentRequest || ($shortConfirmation && $askedBefore)) {
        if ($paymentsAlreadySent && !$requestedResend) {
            sendAndRemember($chatId, "Ya te compartí los medios de pago hace un momento. Avísame si necesitas que te los reenvíe.", $history);
        } else {
            sleep(2); // Pausa natural
            sendAndRemember($chatId, "¡Claro que sí! Aquí tienes los datos 👇", $history);
            sleep(1);
            sendAndRemember($chatId, $PAYMENT_INFO, $history);
        }
        exit;
    }

    // ================== 2. INTELIGENCIA ARTIFICIAL (Cálculos y Conversación) ==================
    
    // Preparamos contextos
    $contextStr = "";
    foreach($PLANS as $p) { 
        $contextStr .= "- {$p['name']}: $".number_format($p['price'], 0, ',', '.')."\n"; 
    }
    
    $indivStr = "";
    foreach($SERVICES as $svc) {
        $indivStr .= "- {$svc['name']}: ".formatCop($svc['price'])."\n";
    }

    // Pausa para simular que "está escribiendo" (más tiempo para parecer más natural)
    sleep(rand(4, 7));

    // Obtener respuesta de GPT
    $aiReply = getAIResponse($textMessage, $contextStr, $indivStr, $history);
    
    // Enviar respuesta
    sendAndRemember($chatId, $aiReply, $history);
}

echo "OK";


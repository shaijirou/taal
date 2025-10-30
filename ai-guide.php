<?php
require_once 'config/config.php';

if (!isset($_SESSION['chatbot_language'])) {
    $_SESSION['chatbot_language'] = 'en';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_language') {
    $_SESSION['chatbot_language'] = ($_SESSION['chatbot_language'] === 'en') ? 'fil' : 'en';
    echo json_encode(['language' => $_SESSION['chatbot_language']]);
    exit();
}

$chat_history = [];
if (isLoggedIn()) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT message, response FROM chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $chat_history = $stmt->fetchAll();
}

function getAIResponse($message, $language = 'en') {
    $message = trim(strtolower($message));

    $yes_responses = [ 'oo sige','sige lang','oo ngani','pwede','yes', 'yep', 'yeah', 'sure', 'okay', 'of course', 'please', 'absolutely', 'definitely', 'affirmative', 'y', 'surely', 'certainly', 'indeed', 'why not', 'by all means', 'ok', 'alright', 'roger that', 'yup', 'aye', 'totally', 'gladly', 'willingly', 'positively', 'undoubtedly', 'unquestionably', 'beyond doubt', 'without a doubt', 'sure thing', 'you bet', 'for sure', 'most definitely', 'certainly so', 'beyond question', 'indubitably', 'assuredly', 'beyond any doubt', 'without question', 'beyond peradventure', 'yes indeed', 'yes sir', 'yes ma\'am', 'affirmatively', 'agreed', 'agreedly', 'by all means', 'clearly', 'evidently', 'manifestly', 'plainly', 'undeniably', 'unmistakably','oo','uh-huh','sige ba', 'sige', 'go ahead', 'let\'s do it', 'i would like that', 'i\'d like that','pwede','oo nga', 'oo naman', 'siyempre', 'talaga', 'tama', 'iyan', 'iyan nga', 'iyan naman', 'gusto ko', 'gusto natin', 'gusto mo', 'sang-ayon ako', 'sang-ayon tayo', 'gutom na ako'];
    
    if (in_array($message, $yes_responses)) {
        if (isset($_SESSION['chat_context'])) {
            $context = $_SESSION['chat_context'];
            unset($_SESSION['chat_context']);

            switch ($context) {
                case 'restaurant':
                    return ($language === 'fil') 
                        ? "Magandang pagpipilian! 🍽️ <a href='places.php?search=&category=restaurant' target='_blank'>I-click dito upang tuklasin ang aming mga restaurant at lokal na eateries.</a>"
                        : "Great choice! 🍽️ <a href='places.php?search=&category=restaurant' target='_blank'>Click here to explore our restaurants and local eateries.</a>";
                case 'hotel':
                    return ($language === 'fil')
                        ? "Napakaganda! 🏨 <a href='places.php?search=&category=accommodation' target='_blank'>Tingnan ang aming mga pagpipilian sa pag-tulugan na may magagandang tanawin.</a>"
                        : "Excellent! 🏨 <a href='places.php?search=&category=accommodation' target='_blank'>View our accommodation options with beautiful views.</a>";
                case 'history':
                    return ($language === 'fil')
                        ? "Perpekto! 🏛️ <a href='places.php?search=&category=historical' target='_blank'>Tuklasin ang aming mga makasaysayang lugar at heritage locations.</a>"
                        : "Perfect! 🏛️ <a href='places.php?search=&category=historical' target='_blank'>Explore our historical sites and heritage locations.</a>";
                case 'lomi':
                    return ($language === 'fil')
                        ? "Siguradong masarap! 🍜 <a href='places.php?search=lomi&category=' target='_blank'>Tingnan ang pinakamahusay na Lomi spots sa Taal.</a>"
                        : "You're in for a treat! 🍜 <a href='places.php?search=lomi&category=' target='_blank'>Check out the best Lomi spots in Taal.</a>";
                case 'map':
                    return ($language === 'fil')
                        ? "Narito ang mapa ng Taal na may mga pangunahing landmark! 🗺️ <a href='map.php' target='_blank'>Tingnan ang Interactive Map</a>"
                        : "Here's the map of Taal with key landmarks! 🗺️ <a href='map.php' target='_blank'>View Interactive Map</a>";
                default:
                    return ($language === 'fil')
                        ? "Makikita mo ang higit pang impormasyon dito: <a href='poi-details.php' target='_blank'>Tingnan ang Lahat ng Points of Interest</a>"
                        : "You can find more information here: <a href='poi-details.php' target='_blank'>View All Points of Interest</a>";
            }
        } else {
            return ($language === 'fil')
                ? "Oo sa ano? 😊 Maaari mo bang sabihin sa akin kung ano ang iyong interes — pagkain, mga hotel, attractions, o tours?"
                : "Yes to what? 😊 Could you tell me more about what you're interested in — food, hotels, attractions, or tours?";
        }
    }

    return getEnhancedLocalResponse($message, $language);
}
function getEnhancedLocalResponse($message, $language = 'en') {
    $message = strtolower($message);

    // ✅ Define keyword groups for easier matching
    $keyword_groups = [
        'heritage town' => ['heritage town', 'bayan', 'makasaysayang bayan', 'lumang bahay', 'pamana'],
        'basilica' => ['basilica', 'simbahan', 'basilika', 'parokya', 'kapilya', 'church', 'cathedral'],
        'lomi' => ['lomi', 'mami', 'pansit'],
        'bulalo' => ['bulalo', 'sabaw ng baka', 'baka soup'],
        'food' => ['food', 'masarap', 'delicious'],
        'kapeng barako' => ['kapeng barako', 'kape', 'barako'],
        'restaurant' => ['restaurant', 'kainan', 'restawran', 'kain', 'pagkain', 'almusal', 'tanghalian', 'hapunan','ulam','menu', 'kumain'],
        'accommodation' => ['accommodation','hotel','guesthouse','inn','homestay','stay','matutuluyan','tutuluyan','kwarto'],
        'history' => ['history', 'kasaysayan', 'historical', 'makasaysayan', 'pamana'],
        'historical site' => ['historical site', 'makasaysayang lugar', 'lumang gusali'],
        'tour' => ['tour', 'tours', 'lakbay', 'ikot', 'biyahe', 'paglalakbay'],
        'weather' => ['weather', 'panahon', 'ulan', 'init', 'klima', 'best time'],
        'transportation' => ['transportation', 'sasakyan', 'biyahe', 'commute', 'paano pumunta', 'direksyon','go to taal','pumunta sa taal','how to get to taal'],
        'bus' => ['bus'],
        'jeep' => ['jeep', 'jeepney'],
        'manila' => ['manila', 'maynila'],
        'batangas' => ['batangas'],
        'map' => ['map', 'mapa', 'lokasyon', 'direksyon', 'gabay'],
        'attraction' => ['lugar', 'destination','pumunta','puntahan', 'pasyalan','place','attraction', 'attractions', 'tourist spot', 'tanawin'],
        'explore' => ['explore', 'tuklasin', 'discover', 'ikot'],
        'thanks' => ['thanks', 'thank you', 'salamat', 'tnx', 'ty', 'maraming salamat'],
        'bye' => ['bye', 'paalam', 'goodbye', 'alis na ako', 'ingat'],
        'help' => ['help', 'tulong', 'assist', 'guidance'],
        'recommend' => ['recommend', 'rekomendasyon', 'suggestion'],
        'suggest' => ['suggest', 'suhestiyon', 'advise'],
        'pera' => ['pera', 'money', 'budget', 'gastos', 'halaga', 'cost', 'price', 'affordable', 'murang', 'how much', 'magkano'],
        'hello' => ['hello', 'hi', 'kumusta', 'kamusta', 'halo', 'hey'],
        'tanginamo' => ['tanginamo', 'putangina', 'pota', 'gago', 'ulol', 'tanga', 'bobo', 'tarantado', 'demonyo', 'hayop', 'pucha', 'putang ina', 'leche', 'anak ng puta', 'pakshet', 'fuck you','bitch','idiot','ulol','fuck']
    ];

    // ✅ Define responses for both EN & FIL
    $responses = [
    'hello' => [
        'en' => "Hello! 👋 Welcome to Taal, Batangas — the Heritage Town of the Philippines! I'm your friendly Chatbot guide, ready to help you discover amazing places, and delicious food here in taal. What would you like to explore today?",
        'fil' => "Halo! 👋 Maligayang pagdating sa Taal, Batangas — ang Heritage Town ng Pilipinas! Ako ang iyong Chatbot guide na handang tumulong sa’yo tuklasin ang magagandang lugar, at masasarap na pagkain sa taal. Ano ang gusto mong tuklasin ngayon?"
    ],
    'tanginamo' => [
        'en' => "I'm here to help you explore Taal in a friendly and respectful manner. Let's keep our conversation positive and enjoyable! 😊",
        // 'fil' => "Tanginamo kang gago kang hayop kang dimonyo kang tarantado ulol pak u tanga ka ulaga"
        'fil' => "Narito ako upang tulungan kang tuklasin ang Taal sa isang magalang at positibong paraan. Panatilihin nating masaya at maganda ang ating pag-uusap! 😊"
    ],
    'heritage town' => [
        'en' => "🏛️ Taal Heritage Town is like stepping back in time! You’ll see ancestral houses from the Spanish era, cobblestone streets, and historical landmarks everywhere. Would you like me to recommend must-visit spots?",
        'fil' => "🏛️ Parang bumalik ka sa nakaraan sa Taal Heritage Town! Makikita mo rito ang mga bahay na pamana noong panahon ng mga Espanyol, makasaysayang gusali, at mga kalsadang bato. Gusto mo bang irekomenda ko ang mga dapat mong bisitahin?"
    ],
    'basilica' => [
        'en' => "⛪ The Taal Basilica, officially the Minor Basilica of St. Martin de Tours, is the largest Catholic church in Asia. It’s a breathtaking site — full of history and devotion.",
        'fil' => "⛪ Ang Taal Basilica o Minor Basilica of St. Martin de Tours ang pinakamalaking simbahanang katolika sa buong Asya. Napakaganda ng tanawin dito  — puno ng kasaysayan at pananampalataya."
    ],
    'food' => [
        'en' => "🍽️ Taal is a food lover’s paradise! From the famous Lomi and Bulalo to Kapeng Barako, there’s something to satisfy every craving. What local dish would you like to know more about?",
        'fil' => "🍽️ Paraiso ang Taal para sa mga mahilig sa pagkain! Mula sa sikat na Lomi at Bulalo hanggang Kapeng Barako, siguradong may makakain kang swak sa iyong panlasa. Anong lokal na putahe ang gusto mong malaman pa?"
    ],
    'lomi' => [
        'en' => "🍜 Taal Lomi is a must-try! This thick and savory noodle soup is loaded with toppings and perfect for rainy days or after a long tour. Want to know the best Lomi spots in town?",
        'fil' => "🍜 Dapat mong matikman ang Taal Lomi! Malapot, malasa, at punong-puno ng sahog — perpekto sa malamig na panahon o pagkatapos mamasyal. Gusto mo bang sabihin ko kung saan pinakamasarap kumain nito?"
    ],
    'bulalo' => [
        'en' => "🍲 Bulalo is a Batangueño classic — rich beef broth with tender bone marrow that warms your soul! Perfect with rice.",
        'fil' => "🍲 Ang Bulalo ay klasikong putahe ng mga Batangueño — mainit na sabaw ng baka na may laman at utak-buto, siguradong pampainit ng katawan!"
    ],
    'kapeng barako' => [
        'en' => "☕ Kapeng Barako is the pride of Batangas! Strong, bold, and aromatic — it’s the perfect drink to start your day, best paired with local delicacies like panutsa or suman.",
        'fil' => "☕ Ipinagmamalaki ng Batangas ang Kapeng Barako! Matapang, mabango, at tunay na gising. Mas masarap kapag sinabayan ng panutsa o suman sa umaga."
    ],
    'restaurant' => [
        'en' => "🍽️ Hungry? Taal is full of cozy restaurants and local eateries offering authentic Batangueño dishes like Lomi, Bulalo, and Kapeng Barako. Want me to recommend one nearby?",
        'fil' => "🍽️ Gutom ka ba? Maraming kainan sa Taal na nag-aalok ng mga putaheng Batangueño tulad ng Lomi, Bulalo, at Kapeng Barako. Gusto mo bang irekomenda ko ang pinakamalapit na kainan?"
    ],
    'accommodation' => [
        'en' => "🏨 Looking for a place to stay? Taal has charming ancestral inns, homestays, and modern resorts where you can rest after a day of exploring. Would you like some top-rated suggestions?",
        'fil' => "🏨 Naghahanap ka ba ng matutuluyan? May mga magaganda at komportableng homestays, heritage inns, at resorts sa Taal kung saan ka pwedeng magpahinga. Gusto mo bang irekomenda ko ang mga pinakamahusay?"
    ],
    'history' => [
        'en' => "📜 Taal is one of the oldest towns in the Philippines — a living museum filled with Spanish-era houses, heroic stories, and cultural pride. Every corner tells a story!",
        'fil' => "📜 Isa ang Taal sa mga pinakamatandang bayan sa Pilipinas — para itong buhay na museo na puno ng mga bahay na pamana, kwento ng kabayanihan, at yaman ng kultura. Bawat kanto ay may kasaysayan!"
    ],
    'tour' => [
        'en' => "🗺️ You can join guided heritage tours, food crawls, or even photo walks around Taal. It’s a fun way to experience the town’s culture and meet friendly locals!",
        'fil' => "🗺️ Pwede kang sumali sa mga heritage tours, food trips, o photo walks sa paligid ng Taal. Masayang paraan ito para maranasan ang kultura ng bayan at makilala ang mga taong Batangueño!"
    ],
    'pera' => [
        'en' => "💰 Taal is very budget-friendly! You can enjoy delicious meals for as low as ₱100, and accommodations range from ₱500 to ₱2,000 per night depending on your preference. Would you like tips on saving money while exploring?",
        'fil' => "💰 Abot-kaya ang Taal! Makakakain ka na ng masarap na pagkain sa halagang ₱100 lang, at ang mga matutuluyan ay nagkakahalaga mula ₱500 hanggang ₱2,000 kada gabi depende sa iyong gusto. Gusto mo bang malaman ang mga tips para makatipid habang nag-eexplore?"
    ],
    'weather' => [
        'en' => "🌤️ The best time to visit Taal is from November to April — sunny skies, cool breeze, and perfect for sightseeing! Want to check today’s weather?",
        'fil' => "🌤️ Pinakamainam bumisita sa Taal mula Nobyembre hanggang Abril — maganda ang panahon at presko ang simoy ng hangin! Gusto mo bang malaman ang lagay ng panahon ngayon?"
    ],
    'transportation' => [
        'en' => "🚌 Getting to Taal is easy! Are you from Manila or Batangas? ",
            'fil' => "🚌 Madali ang pagpunta sa Taal! Ikaw ba ay galing sa Manila o Batangas?"
        ],
    'manila' => [
    'en' => "🚌 From Manila, you can take a bus bound for Lemery, Batangas — that’s the closest terminal to Taal. Bus companies like JAM Liner and DLTB Co. have regular trips from Cubao, Buendia, and LRT Gil Puyat. Once you arrive in Lemery, you can ride a short tricycle trip to Taal town proper.",
    'fil' => "🚌 Mula Manila, sumakay ng bus papuntang Lemery, Batangas — iyon ang pinakamalapit na terminal sa Taal. May mga biyahe ang JAM Liner at DLTB Co. mula Cubao, Buendia, at LRT Gil Puyat. Pagdating mo sa Lemery, sumakay ng tricycle papunta sa bayan ng Taal o bumaba sa may Jollibee at mula doon ay maghintay ng Jeep na dadaan papuntang Lemery at bumaba sa mismong harap ng simbahan ng Taal."
    ],
    'batangas' => [
    'en' => "🚌 From Batangas City, you can take a bus or van heading to Lemery or directly to Taal. Several bus operators like Sunrays Bus and local van services operate regular trips from Batangas City Terminal. The journey takes approximately 30-45 minutes. Alternatively, you can take a jeepney from Batangas City to Lemery, then a short tricycle ride to Taal town proper or directly to the Taal Church.",
    'fil' => "🚌 Mula sa Lungsod ng Batangas, sumakay ng bus o van papuntang Lemery o direkta sa Taal. May mga regular na biyahe ang Sunrays Bus at iba pang local van services mula sa Batangas City Terminal. Ang biyahe ay tumatagal ng humigit-kumulang 30-45 minuto. Bilang alternatibo, sumakay ng jeepney mula Batangas City papunta Lemery, pagkatapos ay tricycle papunta sa bayan ng Taal o direktang bumaba sa simbahan ng Taal"
    ],
    'attraction' => [
        'en' => "🌟 Taal is brimming with attractions — from the majestic Basilica, charming heritage houses. What type of attractions are you interested in?",
        'fil' => "🌟 Puno ang Taal ng mga pasyalan — mula sa napakagandang Basilica, mga makasaysayang bahay. Anong klaseng pasyalan ang gusto mong tuklasin?"
    ],  
    'map' => [
        'en' => "🗺️ I can show you a detailed map of Taal’s landmarks, restaurants, and tourist spots. Would you like me to open it for you?",
        'fil' => "🗺️ Maaari kitang ipakita ng mapa ng mga lugar, kainan, at pasyalan sa Taal. Gusto mo bang buksan ko ito para sa'yo?"
    ],
    'thanks' => [
        'en' => "You're very welcome! 😊 Anything else you'd like to learn or explore about Taal?",
        'fil' => "Walang anuman! 😊 May gusto ka pa bang malaman o tuklasin tungkol sa Taal?"
    ],
    'bye' => [
        'en' => "Goodbye! 👋 Enjoy your visit to Taal, and don’t forget to take lots of pictures!",
        'fil' => "Paalam! 👋 Mag-enjoy ka sa pagbisita sa Taal, at huwag kalimutang mag-picture!"
    ],
    'help' => [
        'en' => "I can assist you with directions, tourist spots, local food, or places to stay. What kind of help do you need?",
        'fil' => "Maaari kitang tulungan sa mga direksyon, pasyalan, pagkain, o matutuluyan. Anong klaseng tulong ang kailangan mo?"
    ],
    'recommend' => [
        'en' => "Of course! 🎯 I can recommend the best restaurants, tours, and hotels in Taal. Which one are you interested in?",
        'fil' => "Siyempre! 🎯 Maaari akong magrekomenda ng mga pinakamahusay na kainan, tours, at hotel sa Taal. Alin ang gusto mong unahin?"
    ],
    'suggest' => [
        'en' => "I’d love to help you plan! 🌟 Are you looking for food, hotels, or tourist attractions?",
        'fil' => "Gusto kong makatulong sa plano mo! 🌟 Interesado ka ba sa mga pagkain, hotel, o mga pasyalan?"
    ],
    'explore' => [
        'en' => "🧭 Let’s explore Taal together! Visit the Basilica, stroll through Heritage Town, and don’t miss trying Lomi or Kapeng Barako.",
        'fil' => "🧭 Tuklasin natin ang Taal! Bisitahin ang Basilica, maglakad-lakad sa Heritage Town, at huwag palampasin ang Lomi o Kapeng Barako."
    ]
];


    // ✅ Keyword matching and context detection
    foreach ($keyword_groups as $key => $keywords) {
        foreach ($keywords as $word) {
            if (strpos($message, $word) !== false) {
                $_SESSION['chat_context'] = $key;
                return $responses[$key][$language] ?? $responses[$key]['en'];
            }
        }
    }

    // ✅ Default fallback response
    return ($language === 'fil')
        ? "Gusto kong tumulong sa iyo na tuklasin ang Taal! 🌟 Magtanong sa akin tungkol sa Heritage Town, lokal na pagkain, mga hotel, tours, o attractions."
        : "I'd love to help you explore Taal! 🌟 Ask me about Heritage Town, local food, hotels, tours, or attractions.";
}



if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $message = sanitizeInput($_POST['message']);
    $language = isset($_POST['language']) ? $_POST['language'] : $_SESSION['chatbot_language'];
    $response = getAIResponse($message, $language);
    

    if (isLoggedIn()) {
        $database = new Database();
        $db = $database->getConnection();

        $query = "INSERT INTO chat_history (user_id, message, response, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id'], $message, $response]);
        logUserActivity($_SESSION['user_id'], 'ai_chat', null, 'Asked: ' . substr($message, 0, 100));
    }

    echo json_encode(['response' => $response]);
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Add language toggle button styling */
        .language-toggle {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            justify-content: center;
        }
        
        .language-btn {
            padding: 0.5rem 1rem;
            border: 2px solid #007bff;
            background-color: white;
            color: #007bff;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .language-btn.active {
            background-color: #007bff;
            color: white;
        }
        
        .language-btn:hover {
            background-color: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <h1 class="text-center mb-3">Your Chatbot Guide</h1>
            
            <!-- Add language toggle buttons -->
            <div class="language-toggle">
                <button class="language-btn <?php echo $_SESSION['chatbot_language'] === 'en' ? 'active' : ''; ?>" data-language="en">English</button>
                <button class="language-btn <?php echo $_SESSION['chatbot_language'] === 'fil' ? 'active' : ''; ?>" data-language="fil">Filipino</button>
            </div>
            
            <div class="chat-container">
                <div class="chat-header">
                    <h3>🤖 Taal Chatbot</h3>
                    <p><?php echo $_SESSION['chatbot_language'] === 'fil' ? 'Ang iyong chatbot guide sa paggalugad ng Taal, Batangas' : 'Your chatbot guide to exploring Taal, Batangas'; ?></p>
                </div>
                
                <div class="chat-messages" id="chat-messages">
                    <div class="message">
                        <div class="message-content">
                            <strong><?php echo $_SESSION['chatbot_language'] === 'fil' ? 'Chatbot:' : 'Chatbot:'; ?></strong> 
                            <?php 
                                $greeting = getAIResponse('hello', $_SESSION['chatbot_language']);
                                echo $greeting;
                            ?>
                        </div>
                    </div>

                    <?php foreach ($chat_history as $chat): ?>
                        <div class="message user">
                            <div class="message-content">
                                <strong>You:</strong> <?php echo htmlspecialchars($chat['message']); ?>
                            </div>
                        </div>
                        <div class="message">
                            <div class="message-content">
                                <strong><?php echo $_SESSION['chatbot_language'] === 'fil' ? 'Chatbot:' : 'Chatbot:'; ?></strong> <?php echo nl2br(htmlspecialchars($chat['response'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="chat-input">
                    <input type="text" id="message-input" placeholder="<?php echo $_SESSION['chatbot_language'] === 'fil' ? 'Magtanong sa akin tungkol sa Taal...' : 'Ask me anything about Taal...'; ?>" maxlength="500">
                    <button id="send-button">
                        <span class="btn-text"><?php echo $_SESSION['chatbot_language'] === 'fil' ? 'Ipadala' : 'Send'; ?></span>
                        <span class="spinner" style="display: none;"></span>
                    </button>
                </div>
            </div>
            
            <div class="card mt-3">
                <h3><?php echo $_SESSION['chatbot_language'] === 'fil' ? 'Mga Popular na Tanong' : 'Popular Questions'; ?></h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem;">
                    <?php 
                        $questions = $_SESSION['chatbot_language'] === 'fil' ? [
                            ['label' => 'Heritage Town', 'question' => 'Ano ang nasa Heritage Town?'],
                            ['label' => 'Pinakamahusay na Lomi', 'question' => 'Saan ako makakakita ng pinakamahusay na lomi?'],
                            ['label' => 'Hotels at Stays', 'question' => 'Magrekomendasyon ng accommodations na may magagandang tanawin'],
                            ['label' => 'Historical Sites', 'question' => 'Anong mga historical sites ang dapat kong bisitahin?'],
                            ['label' => 'Lokal na Pagkain', 'question' => 'Anong lokal na pagkain ang dapat kong subukan?'],
                            ['label' => 'Pinakamahusay na Oras', 'question' => 'Kailan ang pinakamahusay na oras upang bumisita?'],
                            ['label' => 'Paano Makarating', 'question' => 'Paano ako makakarating sa Taal?'],
                        ] : [
                            ['label' => 'Heritage Town', 'question' => "What's in the Heritage Town?"],
                            ['label' => 'Best Lomi Spots', 'question' => 'Where can I try the best lomi?'],
                            ['label' => 'Hotels & Stays', 'question' => 'Recommend accommodations with beautiful views'],
                            ['label' => 'Historical Sites', 'question' => 'What historical sites should I visit?'],
                            ['label' => 'Local Cuisine', 'question' => 'What local foods should I try?'],
                            ['label' => 'Best Time to Visit', 'question' => "What's the best time to visit?"],
                            ['label' => 'Getting Here', 'question' => 'How do I get to Taal?'],
                        ];
                        
                        foreach ($questions as $q):
                    ?>
                        <button class="btn btn-primary quick-question" data-question="<?php echo htmlspecialchars($q['question']); ?>"><?php echo htmlspecialchars($q['label']); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        const chatMessages = document.getElementById('chat-messages');
        const messageInput = document.getElementById('message-input');
        const sendButton = document.getElementById('send-button');
        const btnText = sendButton.querySelector('.btn-text');
        const spinner = sendButton.querySelector('.spinner');
        let currentLanguage = '<?php echo $_SESSION['chatbot_language']; ?>';

        document.querySelectorAll('.language-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const newLanguage = this.dataset.language;
                
                fetch('ai-guide.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=toggle_language'
                })
                .then(response => response.json())
                .then(data => {
                    currentLanguage = data.language;
                    document.querySelectorAll('.language-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update placeholder and button text
                    messageInput.placeholder = currentLanguage === 'fil' ? 'Magtanong sa akin tungkol sa Taal...' : 'Ask me anything about Taal...';
                    btnText.textContent = currentLanguage === 'fil' ? 'Ipadala' : 'Send';
                    
                    // Show language change message
                    const langMessage = currentLanguage === 'fil' 
                        ? 'Ang wika ay nabago sa Filipino. 🇵🇭' 
                        : 'Language changed to English. 🇺🇸';
                    addMessage(langMessage);
                });
            });
        });

        function addMessage(content, isUser = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isUser ? 'user' : ''}`;
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            contentDiv.innerHTML = `<strong>${isUser ? 'You' : 'Chatbot'}:</strong> ${content}`;
            messageDiv.appendChild(contentDiv);
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function setLoading(loading) {
            sendButton.disabled = loading;
            btnText.style.display = loading ? 'none' : 'inline';
            spinner.style.display = loading ? 'inline-block' : 'none';
        }

        function sendMessage() {
            const message = messageInput.value.trim();
            if (!message) return;
            addMessage(message, true);
            messageInput.value = '';
            setLoading(true);

            fetch('ai-guide.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'message=' + encodeURIComponent(message) + '&language=' + encodeURIComponent(currentLanguage)
            })
            .then(response => response.json())
            .then(data => {
                addMessage(data.response.replace(/\n/g, '<br>'));
                setLoading(false);
            })
            .catch(() => {
                const errorMsg = currentLanguage === 'fil' 
                    ? 'Paumanhin, nakaencounter ako ng error. Subukan ulit.' 
                    : 'Sorry, I encountered an error. Please try again.';
                addMessage(errorMsg);
                setLoading(false);
            });
        }

        sendButton.addEventListener('click', sendMessage);
        messageInput.addEventListener('keypress', e => {
            if (e.key === 'Enter' && !sendButton.disabled) sendMessage();
        });

        document.querySelectorAll('.quick-question').forEach(button => {
            button.addEventListener('click', () => {
                messageInput.value = button.dataset.question;
                sendMessage();
            });
        });
    </script>
</body>
</html>

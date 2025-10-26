<?php
require_once 'config/config.php';

// Local AI Tour Guide responses (offline)
function getAIResponse($message) {
    $message = trim(strtolower($message));

    // Handle yes/no follow-up logic
    if (in_array($message, ['yes', 'yep', 'yeah', 'sure', 'okay', 'of course', 'please', 'absolutely', 'definitely', 'affirmative', 'y', 'surely', 'certainly', 'indeed', 'why not', 'by all means', 'ok', 'alright', 'roger that', 'yup', 'aye', 'totally', 'gladly', 'willingly', 'positively', 'undoubtedly', 'unquestionably', 'beyond doubt', 'without a doubt', 'sure thing', 'you bet', 'for sure', 'most definitely', 'certainly so', 'beyond question', 'indubitably', 'assuredly', 'beyond any doubt', 'without question', 'beyond peradventure', 'yes indeed', 'yes sir', 'yes ma\'am', 'affirmatively', 'agreed', 'agreedly', 'by all means', 'clearly', 'evidently', 'manifestly', 'plainly', 'undeniably', 'unmistakably','oo','uh-huh','sige ba', 'sige', 'go ahead', 'let\'s do it', 'i would like that', 'i\'d like that','pwede','oo nga', 'oo naman', 'siyempre', 'talaga', 'tama', 'iyan', 'iyan nga', 'iyan naman', 'gusto ko', 'gusto natin', 'gusto mo', 'sang-ayon ako', 'sang-ayon tayo'])) {
        if (isset($_SESSION['chat_context'])) {
            $context = $_SESSION['chat_context'];
            unset($_SESSION['chat_context']); // clear context after redirect

            // Redirect links depending on last asked topic
            switch ($context) {
                case 'restaurant':
                    return "Here's a great place you can check out! 🍽️ <a href='places.php?search=&category=restaurant' target='_blank'>Click here to view the restaurant details.</a>";
                case 'hotel':
                    return "Great! 🏨 You can explore our beautiful hotels here: <a href='places.php?search=&category=accommodation' target='_blank'>View Hotel Details</a>";
               case 'history':
                    return "Perfect! 🏛️ Here’s a historical site worth visiting: <a href='places.php?search=&category=historical' target='_blank'>View Historical Site</a>";
                case 'lomi':
                    return "You’ll love this spot for Lomi! 🍜 <a href='poi-details.php?id=6' target='_blank'>Click here for details.</a>";
                case 'activities':
                    return "Here are some exciting activities you can enjoy in Taal! 🎉 <a href='activities.php' target='_blank'>View Activities</a>";
                case 'map':
                    return "Here's the map of Taal with key landmarks highlighted! 🗺️ <a href='map.php' target='_blank'>View Map</a>";
                default:
                    return "Sure! You can find more information here: <a href='poi-details.php' target='_blank'>View All Points of Interest</a>";
            }
        } else {
            return "Yes to what? 😊 Could you tell me more about what you're interested in — food, hotels, or attractions?";
        }
    }

    // Normal response path
    return getEnhancedLocalResponse($message);
}

// Local predefined and enhanced responses
function getEnhancedLocalResponse($message) {
    $responses = [
        'hello' => "Hello! Welcome to Taal, Batangas!  I'm your AI guide ready to help you explore our beautiful municipality. I can assist with information about Taal Church, local attractions, restaurants, accommodations, and travel tips. What would you like to discover today?",
        'church' => "Taal Basilica is a must-visit! ⛪ It's the largest church in Asia and a beautiful example of Baroque architecture. Would you like directions to Taal Basilica?",
        'lomi' => "Lomi is a must-try dish in Taal! 🍜 It's a hearty noodle soup perfect for the local climate. Would you like me to recommend the best places to try Lomi?",
        
        'restaurant' => "Taal has amazing local cuisine! 🍜 Try Lomi, Bulalo, and Kapeng Barako! Would you like directions to any specific restaurant or more food recommendations?",
        
        'accommodation' => "Here are great accommodation options in Taal 🏨 — MGM's Farm, Villa Tortuga, and budget homestays. Would you like me to show you one nearby?",
        'historical site' => "Taal is rich in history! 🏛️ Don't miss the Taal Basilica, Casa Villavicencio, and the Taal Heritage Town. Would you like directions to any of these sites?",
        
        'history' => "Taal's history is fascinating! 🏛️ Founded in 1572 and rich with colonial architecture. Would you like to see a famous historical site?",
          
        'food' => "🍽️ Must-try dishes in Taal include Lomi, Bulalo, and Kapeng Barako! Would you like directions to the best restaurant?",
        
        'weather' => "🌤️ Best time to visit is November to April (dry season). Do you want me to recommend activities for this season?",
        
         'how to get' => "You can get to Taal via bus to Lemery then a short tricycle ride. Would you like a step-by-step route guide?",
        'coffee' => "Kapeng Barako is a local favorite! ☕ Would you like me to recommend a great café to try it?",
        'bulalo' => "Bulalo is a must-try in Taal! 🍲 Would you like directions to a popular Bulalo restaurant?",
        'kape' => "Kapeng Barako is a strong local coffee! ☕ Would you like me to suggest a café to try it?",
        'place' => "Taal offers beautiful spots like Taal Church, Heritage Town, and museums. Would you like to see these places?",
        // General Help
        'spots' => "Here are top spots in Taal 🌋 — Volcano Island, Heritage Town, Basilica, and Taal Lake. Would you like a tour link?",
        'tourist' => "Taal has scenic views, cultural houses, and great food! 🗺️ Would you like me to plan a simple tour for you?",
        'explore' => "Let’s explore Taal! 🧭 Do you want to start with attractions, food, or hotels?",
        'map' => "🗺️ I can show you a map of Taal’s key landmarks. Would you like to open it?",
        'thanks' => "You're welcome! 😊 Anything else you’d like to know about Taal?",
        'thank you' => "You're most welcome! 🙏 Enjoy exploring Taal!",
        'bye' => "Goodbye! 👋 Hope to see you again exploring Taal soon!",
        'ty' => "You're most welcome! 🙏 Enjoy exploring Taal!"
    ];

    foreach ($responses as $keyword => $response) {
        if (strpos($message, $keyword) !== false) {
            // Save conversation context for “yes” follow-up
            if (in_array($keyword, ['restaurant', 'food', 'kape', 'bulalo'])) {
                $_SESSION['chat_context'] = 'restaurant';
            } elseif (in_array($keyword, ['accommodation', 'hotel'])) {
                $_SESSION['chat_context'] = 'hotel';
            } elseif ($keyword === 'lomi') {
                $_SESSION['chat_context'] = 'lomi';
            } elseif ($keyword === 'weather') {
                $_SESSION['chat_context'] = 'activities';
            } elseif ($keyword === 'volcano') {
                $_SESSION['chat_context'] = 'volcano';
            } elseif ($keyword === 'history' || $keyword === 'historical site' || $keyword === 'place') {
                $_SESSION['chat_context'] = 'history';
            }
            elseif ($keyword === 'map'){
                $_SESSION['chat_context'] = 'map';
            }
            

            return $response;
        }
    }

    return "I'd love to help you explore Taal! Ask me about food, hotels, or local attractions!";
}

// Handle AJAX chat requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $message = sanitizeInput($_POST['message']);
    $response = getAIResponse($message);
    
    // Save chat if logged in
    if (isLoggedIn()) {
        $database = new Database();
        $db = $database->getConnection();

        $query = "INSERT INTO chat_history (user_id, message, response, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id'], $message, $response]);
        logUserActivity($_SESSION['user_id'], 'ai_chat', 'Asked: ' . substr($message, 0, 100));
    }

    echo json_encode(['response' => $response]);
    exit();
}

// Load chat history
$chat_history = [];
if (isLoggedIn()) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT message, response, created_at FROM chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $chat_history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Tour Guide - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <h1 class="text-center mb-3">Chat with Your AI Tour Guide</h1>
            
            <div class="chat-container">
                <div class="chat-header">
                    <h3>Ala Eh! AI Assistant</h3>
                    <p>Your intelligent guide to exploring Taal, Batangas</p>
                </div>
                
                <div class="chat-messages" id="chat-messages">
                    <div class="message">
                        <div class="message-content">
                            <strong>AI Guide:</strong> Hello! Welcome to Taal, Batangas! I'm your AI guide ready to help you explore our beautiful municipality. I can assist with information about Taal Volcano, local attractions, restaurants, accommodations, and travel tips. What would you like to discover today?
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
                                <strong>AI Guide:</strong> <?php echo nl2br(htmlspecialchars($chat['response'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="chat-input">
                    <input type="text" id="message-input" placeholder="Ask me anything about Taal..." maxlength="500">
                    <button id="send-button">
                        <span class="btn-text">Send</span>
                        <span class="spinner" style="display: none;"></span>
                    </button>
                </div>
            </div>
            
            <div class="card mt-3">
                <h3>Popular Questions</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem;">
                    <button class="btn btn-primary quick-question" data-question="Where can I try the best lomi?">Best Lomi Spots</button>
                    <button class="btn btn-primary quick-question" data-question="Recommend accommodations with beautiful views">Hotels and Accommodation</button>
                    <button class="btn btn-primary quick-question" data-question="What historical sites should I visit?">Historical Sites</button>
                    <button class="btn btn-primary quick-question" data-question="What local foods should I try?">Local Cuisine</button>
                    <button class="btn btn-primary quick-question" data-question="What's the weather like and when should I visit?">Weather & Best Time</button>
                    
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

        function addMessage(content, isUser = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isUser ? 'user' : ''}`;
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            contentDiv.innerHTML = `<strong>${isUser ? 'You' : 'AI Guide'}:</strong> ${content}`;
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
                body: 'message=' + encodeURIComponent(message)
            })
            .then(response => response.json())
            .then(data => {
                addMessage(data.response.replace(/\n/g, '<br>'));
                setLoading(false);
            })
            .catch(() => {
                addMessage('Sorry, I encountered an error. Please try again.');
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

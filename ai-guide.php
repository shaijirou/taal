<?php
require_once 'config/config.php';

// Enhanced AI responses using Google Gemini API (free tier)
function getAIResponse($message) {
    $message = trim($message);
    
    // Check if we have a Gemini API key configured
    $gemini_api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null;
    
    if ($gemini_api_key) {
        return getGeminiResponse($message, $gemini_api_key);
    } else {
        // Fallback to enhanced local responses if no API key
        return getEnhancedLocalResponse($message);
    }
}

function getGeminiResponse($message, $api_key) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $api_key;
    
    // Create context-aware prompt for tourism
    $system_prompt = "You are an AI tour guide for Taal, Batangas, Philippines. You are knowledgeable about:
- Taal Volcano and its history
- Local attractions, restaurants, and accommodations
- Cultural sites and historical landmarks
- Local cuisine (lomi, bulalo, kapeng barako)
- Transportation and directions
- Weather and travel tips
- Safety information for volcano visits

Provide helpful, accurate, and friendly responses. Keep responses concise but informative. Use emojis sparingly and appropriately.";
    
    $prompt = $system_prompt . "\n\nUser question: " . $message;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 500
        ]
    ];
    
    $options = [
        'http' => [
            'header' => [
                "Content-Type: application/json",
                "User-Agent: TaalTourismApp/1.0"
            ],
            'method' => 'POST',
            'content' => json_encode($data),
            'timeout' => 30
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        return getEnhancedLocalResponse($message);
    }
    
    $response = json_decode($result, true);
    
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        return $response['candidates'][0]['content']['parts'][0]['text'];
    } else {
        return getEnhancedLocalResponse($message);
    }
}

function getEnhancedLocalResponse($message) {
    $message = strtolower($message);
    
    // Enhanced predefined responses with more context
    $responses = [
        'hello' => "Hello! Welcome to Taal, Batangas! 🌋 I'm your AI guide ready to help you explore our beautiful municipality. I can assist with information about Taal Volcano, local attractions, restaurants, accommodations, and travel tips. What would you like to discover today?",
        
        'hi' => "Hi there! Great to meet you! I'm here to help you make the most of your visit to Taal. Whether you're interested in volcano tours, local cuisine, or cultural sites, I've got you covered. What can I help you with?",
        
        'taal volcano' => "Taal Volcano is truly spectacular! 🌋 It's one of the world's smallest active volcanoes, located on an island within Taal Lake. Here's what you need to know:\n\n• Best visited early morning (6-8 AM) for cooler weather\n• Boat ride from Talisay takes 20-30 minutes\n• Horseback riding available to the crater rim\n• Entry fee: ₱50 for boat, ₱500-800 for horse\n• Bring water, hat, and comfortable shoes\n• Check volcanic activity status before visiting\n\nWould you like specific directions or tour operator recommendations?",
        
        'restaurant' => "Taal has amazing local cuisine! 🍜 Here are my top recommendations:\n\n**Must-try dishes:**\n• Lomi - thick noodle soup (try Lomi King)\n• Bulalo - beef bone marrow soup\n• Kapeng Barako - strong local coffee\n• Fresh fish from Taal Lake\n\n**Popular spots:**\n• Lomi King - famous for authentic lomi\n• Local eateries along the town proper\n• Lakeside restaurants with volcano views\n\nWould you like directions to any specific restaurant or more food recommendations?",
        
        'accommodation' => "Great accommodation options in Taal! 🏨\n\n**Luxury:**\n• Villa Tortuga - lakeside resort with volcano views\n• Taal Vista Hotel - historic hotel with panoramic views\n\n**Budget-friendly:**\n• Local inns in town proper\n• Guesthouses near the lake\n• Homestays for authentic experience\n\n**Booking tips:**\n• Reserve in advance during peak season\n• Many offer volcano tour packages\n• Ask about lake view rooms\n\nWould you like contact information or help with specific dates?",
        
        'history' => "Taal's history is fascinating! 🏛️\n\n**Key highlights:**\n• Founded in 1572 by Spanish colonizers\n• One of the oldest towns in Batangas\n• Rich Spanish colonial architecture preserved\n• Basilica of St. Martin de Tours - one of Asia's largest churches\n• Heritage houses showcase Filipino-Spanish architecture\n• Survived multiple volcanic eruptions\n\n**Must-visit historical sites:**\n• Basilica of St. Martin de Tours\n• Ancestral houses along Calle Gliceria Marella\n• Old town plaza and municipal building\n\nWould you like a walking tour route or specific historical site details?",
        
        'directions' => "I'd be happy to help with directions! 🗺️\n\n**Getting to Taal:**\n• From Manila: 2-3 hours by car via SLEX-STAR Tollway\n• Bus: Take trips to Lemery or Tanauan, then jeepney to Taal\n• From Tagaytay: 30-45 minutes by car\n\n**Within Taal:**\n• Tricycles available for short distances\n• Walking tours for the heritage town\n• Boats to Volcano Island from Talisay\n\nWhere specifically would you like to go? I can provide detailed directions and transportation options.",
        
        'weather' => "Taal weather guide! 🌤️\n\n**Best time to visit:**\n• Dry season: November to April\n• Cooler months: December to February\n• Avoid rainy season: June to October\n\n**Daily patterns:**\n• Mornings: Cool and clear (best for volcano visits)\n• Afternoons: Can get hot and humid\n• Evenings: Pleasant and breezy\n\n**What to bring:**\n• Light, breathable clothing\n• Hat and sunscreen\n• Light jacket for early morning\n• Umbrella during rainy season\n\nWould you like current weather conditions or specific activity recommendations?",
        
        'food' => "Taal's culinary scene is incredible! 🍽️\n\n**Local specialties:**\n• **Lomi** - Thick egg noodles in rich broth with toppings\n• **Bulalo** - Beef shank and bone marrow soup\n• **Kapeng Barako** - Strong, aromatic local coffee\n• **Tawilis** - Endemic fish from Taal Lake\n• **Maliputo** - Another local lake fish delicacy\n\n**Where to try them:**\n• Street food stalls for authentic lomi\n• Lakeside restaurants for fresh fish\n• Local cafes for kapeng barako\n• Carinderias for home-style cooking\n\nAny specific dish you'd like to try? I can recommend the best places!",
        
        'safety' => "Safety tips for your Taal visit! ⚠️\n\n**Volcano safety:**\n• Check PHIVOLCS alerts before visiting\n• Follow guide instructions at all times\n• Don't venture beyond designated areas\n• Bring plenty of water\n• Inform someone of your plans\n\n**General safety:**\n• Keep valuables secure\n• Use registered tour operators\n• Have emergency contacts ready\n• Bring first aid basics\n• Stay hydrated\n\n**Emergency contacts:**\n• Tourist Police: Available at major sites\n• Local Emergency: 911\n• PHIVOLCS: Check online for volcano updates\n\nNeed specific safety information for any activity?",
        
        'transportation' => "Getting around Taal! 🚗\n\n**To Taal:**\n• Private car: Most convenient, 2-3 hours from Manila\n• Bus: JAM Transit, DLTB to Lemery/Tanauan\n• Van: From Cubao or Buendia\n\n**Within Taal:**\n• Tricycle: ₱10-50 for short distances\n• Jeepney: For longer routes\n• Walking: Best for heritage town exploration\n• Habal-habal: For rough terrain\n\n**To Volcano Island:**\n• Boat from Talisay: ₱50 per person\n• Tour packages available\n• Operating hours: 6 AM - 5 PM\n\nNeed help planning your route or transportation budget?"
    ];
    
    // Enhanced keyword matching with partial matches
    foreach ($responses as $keyword => $response) {
        if (strpos($message, $keyword) !== false) {
            return $response;
        }
    }
    
    // Check for common question patterns
    if (strpos($message, 'how to get') !== false || strpos($message, 'how do i get') !== false) {
        return $responses['directions'];
    }
    
    if (strpos($message, 'where to eat') !== false || strpos($message, 'food') !== false) {
        return $responses['food'];
    }
    
    if (strpos($message, 'where to stay') !== false || strpos($message, 'hotel') !== false) {
        return $responses['accommodation'];
    }
    
    if (strpos($message, 'safe') !== false || strpos($message, 'danger') !== false) {
        return $responses['safety'];
    }
    
    // Default enhanced response
    return "I'd love to help you explore Taal! 🌋 Here's what I can assist you with:\n\n🌋 **Taal Volcano** - Tours, safety tips, best times to visit\n🍜 **Local Cuisine** - Lomi, bulalo, kapeng barako, and where to find them\n🏨 **Accommodations** - From luxury resorts to budget-friendly options\n🏛️ **Historical Sites** - Colonial architecture, churches, heritage houses\n🗺️ **Directions & Transportation** - How to get here and around town\n🌤️ **Weather & Travel Tips** - Best times to visit, what to bring\n⚠️ **Safety Information** - Volcano alerts, general travel safety\n\nJust ask me about any of these topics, or tell me what specific information you need about Taal!";
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $message = sanitizeInput($_POST['message']);
    $response = getAIResponse($message);
    
    // Save chat history if user is logged in
    if (isLoggedIn()) {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "INSERT INTO chat_history (user_id, message, response, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id'], $message, $response]);
        
        // Also log the AI chat activity
        logUserActivity($_SESSION['user_id'], 'ai_chat', 'Asked: ' . substr($message, 0, 100));
    }
    
    echo json_encode(['response' => $response]);
    exit();
}

// Get chat history if user is logged in
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
                     Welcome message 
                    <div class="message">
                        <div class="message-content">
                            <strong>AI Guide:</strong> Hello! Welcome to Taal, Batangas! I'm your AI guide ready to help you explore our beautiful municipality. I can assist with information about Taal Volcano, local attractions, restaurants, accommodations, and travel tips. What would you like to discover today?
                        </div>
                    </div>
                    
                     Load chat history 
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
            
             Enhanced Quick suggestions 
            <div class="card mt-3">
                <h3>Popular Questions</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem;">
                    <button class="btn btn-primary quick-question" data-question="Tell me about Taal Volcano tours">Volcano Tours</button>
                    <button class="btn btn-primary quick-question" data-question="Where can I try the best lomi?">Best Lomi Spots</button>
                    <button class="btn btn-primary quick-question" data-question="Recommend accommodations with lake views">Lake View Hotels</button>
                    <button class="btn btn-primary quick-question" data-question="What historical sites should I visit?">Historical Sites</button>
                    <button class="btn btn-primary quick-question" data-question="How do I get to Taal from Manila?">Transportation</button>
                    <button class="btn btn-primary quick-question" data-question="What's the weather like and when should I visit?">Weather & Best Time</button>
                    <button class="btn btn-primary quick-question" data-question="Safety tips for visiting Taal Volcano">Safety Information</button>
                    <button class="btn btn-primary quick-question" data-question="What local foods should I try?">Local Cuisine</button>
                </div>
            </div>
            
             API Status Info 
            <div class="card mt-3" style="background: #f8f9fa; border: 1px solid #e9ecef;">
                <h4 style="margin-bottom: 1rem; color: #495057;">AI Assistant Status</h4>
                <p style="margin-bottom: 0.5rem; color: #6c757d;">
                    <?php if (defined('GEMINI_API_KEY') && GEMINI_API_KEY): ?>
                        <span style="color: #28a745; font-weight: 600;">🟢 Enhanced AI Active</span> - Powered by Google Gemini for intelligent responses
                    <?php else: ?>
                        <span style="color: #ffc107; font-weight: 600;">🟡 Local AI Active</span> - Using enhanced local knowledge base
                    <?php endif; ?>
                </p>
                <p style="margin: 0; font-size: 0.875rem; color: #6c757d;">
                    To enable advanced AI responses, add your Google Gemini API key to the configuration.
                    <a href="https://makersuite.google.com/app/apikey" target="_blank" style="color: #007bff;">Get free API key</a>
                </p>
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
            if (loading) {
                btnText.style.display = 'none';
                spinner.style.display = 'inline-block';
                sendButton.style.opacity = '0.7';
            } else {
                btnText.style.display = 'inline';
                spinner.style.display = 'none';
                sendButton.style.opacity = '1';
            }
        }
        
        function sendMessage() {
            const message = messageInput.value.trim();
            if (!message) return;
            
            // Add user message
            addMessage(message, true);
            messageInput.value = '';
            setLoading(true);
            
            // Send to server
            fetch('ai-guide.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'message=' + encodeURIComponent(message)
            })
            .then(response => response.json())
            .then(data => {
                addMessage(data.response.replace(/\n/g, '<br>'));
                setLoading(false);
            })
            .catch(error => {
                console.error('Error:', error);
                addMessage('Sorry, I encountered an error. Please try again.');
                setLoading(false);
            });
        }
        
        // Event listeners
        sendButton.addEventListener('click', sendMessage);
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !sendButton.disabled) {
                sendMessage();
            }
        });
        
        // Quick questions
        document.querySelectorAll('.quick-question').forEach(button => {
            button.addEventListener('click', () => {
                messageInput.value = button.dataset.question;
                sendMessage();
            });
        });
        
        // Auto-scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        // Auto-focus input
        messageInput.focus();
    </script>
</body>
</html>

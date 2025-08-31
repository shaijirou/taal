<?php
require_once 'config/config.php';

// Simple AI responses (in a real application, you would integrate with an actual AI service)
function getAIResponse($message) {
    $message = strtolower($message);
    
    // Predefined responses for common queries
    $responses = [
        'hello' => "Hello! I'm your AI guide for Taal, Batangas! 🌋 I can help you discover amazing places, recommend restaurants, provide directions, and share interesting facts about our beautiful municipality. What would you like to know?",
        'hi' => "Hi there! Welcome to Taal! How can I assist you in exploring our wonderful town today?",
        'taal volcano' => "Taal Volcano is one of the most active volcanoes in the Philippines! 🌋 It's located in the middle of Taal Lake and offers breathtaking views. You can take a boat ride to Volcano Island and hike to the crater. The best time to visit is early morning. Would you like directions or more information about boat tours?",
        'restaurant' => "Taal has amazing local cuisine! 🍜 I highly recommend trying the famous Batangas lomi at Lomi King, or visit local eateries for authentic bulalo and kapeng barako. Would you like specific restaurant recommendations or directions to any particular place?",
        'accommodation' => "For accommodation, I recommend Villa Tortuga for a luxurious lake view experience, or there are several budget-friendly inns in the town proper. Would you like me to show you locations on the map or provide contact information?",
        'history' => "Taal is rich in history! 🏛️ The town was established in 1572 and features well-preserved Spanish colonial architecture. The Basilica of St. Martin de Tours is one of the largest churches in Asia. The heritage town showcases beautiful ancestral houses. Would you like to know about specific historical sites?",
        'directions' => "I can help you with directions! 🗺️ Please tell me your destination or use the interactive map feature. You can also enable location services to get real-time GPS navigation to any point of interest.",
        'weather' => "Taal generally has a tropical climate. The best time to visit is during the dry season (November to April). Always check current weather conditions before visiting Taal Volcano. Would you like tips for any specific activities?",
        'food' => "Taal's local specialties include lomi, bulalo, kapeng barako, and fresh fish from Taal Lake! 🐟 Don't miss trying the local delicacies. Would you like restaurant recommendations or directions to specific eateries?"
    ];
    
    // Check for keyword matches
    foreach ($responses as $keyword => $response) {
        if (strpos($message, $keyword) !== false) {
            return $response;
        }
    }
    
    // Default response
    return "I'd be happy to help you explore Taal! You can ask me about:\n\n🌋 Taal Volcano and attractions\n🍜 Local restaurants and food\n🏨 Accommodations\n🏛️ Historical sites and culture\n🗺️ Directions and navigation\n🌤️ Weather and travel tips\n\nWhat would you like to know more about?";
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $message = sanitizeInput($_POST['message']);
    $response = getAIResponse($message);
    
    // Save chat history if user is logged in
    if (isLoggedIn()) {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "INSERT INTO chat_history (user_id, message, response) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id'], $message, $response]);
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
            <h1 class="text-center mb-3">Chat with Your AI Tour Guide 🤖</h1>
            
            <div class="chat-container">
                <div class="chat-header">
                    <h3>Ala Eh! AI Assistant</h3>
                    <p>Your friendly guide to exploring Taal, Batangas</p>
                </div>
                
                <div class="chat-messages" id="chat-messages">
                    <!-- Welcome message -->
                    <div class="message">
                        <div class="message-content">
                            <strong>AI Guide:</strong> Hello! I'm your AI guide for Taal, Batangas! 🌋 I can help you discover amazing places, recommend restaurants, provide directions, and share interesting facts about our beautiful municipality. What would you like to know?
                        </div>
                    </div>
                    
                    <!-- Load chat history -->
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
                    <button id="send-button">Send</button>
                </div>
            </div>
            
            <!-- Quick suggestions -->
            <div class="card mt-3">
                <h3>Quick Questions</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    <button class="btn btn-primary quick-question" data-question="Tell me about Taal Volcano">🌋 Taal Volcano</button>
                    <button class="btn btn-primary quick-question" data-question="Recommend restaurants">🍜 Restaurants</button>
                    <button class="btn btn-primary quick-question" data-question="Where to stay">🏨 Accommodations</button>
                    <button class="btn btn-primary quick-question" data-question="Historical sites">🏛️ History</button>
                    <button class="btn btn-primary quick-question" data-question="How's the weather">🌤️ Weather</button>
                    <button class="btn btn-primary quick-question" data-question="Local food specialties">🍽️ Local Food</button>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        const chatMessages = document.getElementById('chat-messages');
        const messageInput = document.getElementById('message-input');
        const sendButton = document.getElementById('send-button');
        
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
        
        function sendMessage() {
            const message = messageInput.value.trim();
            if (!message) return;
            
            // Add user message
            addMessage(message, true);
            messageInput.value = '';
            sendButton.disabled = true;
            sendButton.textContent = 'Sending...';
            
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
                sendButton.disabled = false;
                sendButton.textContent = 'Send';
            })
            .catch(error => {
                console.error('Error:', error);
                addMessage('Sorry, I encountered an error. Please try again.');
                sendButton.disabled = false;
                sendButton.textContent = 'Send';
            });
        }
        
        // Event listeners
        sendButton.addEventListener('click', sendMessage);
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
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
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit - {{ $document->title }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/app.js'])
</head>
<body>
    <div style="padding: 20px; font-family: Arial, sans-serif;">
        <h1>Editing: {{ $document->title }}</h1>
        
        <div style="margin-bottom: 10px;">
            <strong>Document ID:</strong> {{ $document->id }} |
            <strong>Versi:</strong> <span id="versionNumber">{{ $document->current_version }}</span> |
            <a href="{{ route('documents.history', $document->id) }}">📜 View Version History</a> |
            <a href="{{ route('documents.index') }}">← Back to Documents</a>
        </div>
        
        <hr>
        
        <div style="margin: 10px 0; padding: 10px; border: 1px solid #ccc; background: #f9f9f9;">
            <h3 style="margin: 0 0 5px 0;">👥 Active Users (<span id="userCount">1</span>):</h3>
            <ul id="activeUsersList" style="margin: 0;">
                <li>You (<span id="currentUserName">Loading...</span>)</li>
            </ul>
        </div>
               
        <div>
            <label><strong>Document Content:</strong></label><br>
            <textarea id="editor" rows="25" cols="100" style="width: 100%; padding: 10px; font-family: monospace; border: 1px solid #ccc;">{{ $document->content ?? '' }}</textarea>
        </div>
        
        <div style="margin-top: 10px;">
            <button onclick="manualSave()" style="background: #4CAF50; color: white; padding: 8px 16px; border: none; cursor: pointer;">💾 Save as New Version</button>
            <span id="saveStatus" style="margin-left: 10px; color: gray;"></span>
        </div>
        
        <div style="margin-top: 20px;">
            <h4>📝 Activity / Conflict Log (who edited what):</h4>
            <div id="conflictLog" style="border:1px solid #ccc; padding: 10px; height: 150px; overflow-y: auto; background: #fafafa; font-size: 12px;">
                <div><small>[System]</small> Ready to edit. WebSocket connected.</div>
            </div>
        </div>
    </div>

    {{-- Pass PHP variables to JavaScript --}}
    <script>
        window.docId = @json($document->id);
        window.docVersion = @json($document->current_version);
    </script>

    <script>
        const textareaEditor = document.getElementById('editor');
        textareaEditor.addEventListener('keyup',
        ()=>{
            console.log("Posisi cursor:", textareaEditor.selectionStart);
        });
        // ========== KONFIGURASI ==========
        const docId = window.docId;
        let currentVersion = window.docVersion;
        
        // User info
        const userId = 'user_' + Math.random().toString(36).substr(2, 8);
        let userName = localStorage.getItem(`doc_${docId}_username`);
        
        if (!userName) {
            userName = prompt("Masukkan nama Anda:", "User_" + userId.substr(-4)) || "Anonymous";
            localStorage.setItem(`doc_${docId}_username`, userName);
        }
        
        document.getElementById('currentUserName').innerText = userName;
        
        let lastSavedContent = document.getElementById('editor').value;
        let autoSaveTimer = null;
        
        // Active users dengan struktur yang lebih lengkap
        let activeUsers = [{
            id: userId,
            name: userName,
            cursorPosition: 0
        }];
        
        // Global activity array to store all activities
        let allActivities = [];
        
        // Track user heartbeats
        let userHeartbeats = {};
        
        // ========== FUNGSI ACTIVITY LOG ==========
        
        // Load activities from localStorage
        function loadActivities() {
            const saved = localStorage.getItem(`doc_${docId}_activities`);
            if (saved) {
                allActivities = JSON.parse(saved);
                renderActivities();
            }
        }
        
        // Save activities to localStorage
        function saveActivities() {
            // Keep only last 100 activities
            if (allActivities.length > 100) {
                allActivities = allActivities.slice(-100);
            }
            localStorage.setItem(`doc_${docId}_activities`, JSON.stringify(allActivities));
        }
        
        // Get icon based on activity type
        function getActivityIcon(type) {
            const icons = {
                'edit': '✏️',
                'save': '💾',
                'cursor': '🖱️',
                'info': 'ℹ️',
                'conflict': '⚠️',
                'delete': '🗑️',
                'join': '👤',
                'leave': '👋'
            };
            return icons[type] || '📝';
        }
        
        // Render all activities
        function renderActivities() {
            var logDiv = document.getElementById('conflictLog');
            logDiv.innerHTML = '';
            
            allActivities.forEach(function(activity) {
                var div = document.createElement('div');
                var icon = getActivityIcon(activity.type);
                var isCurrentUser = activity.userId === userId;
                var userNameDisplay = isCurrentUser ? `${activity.userName} (You)` : activity.userName;
                
                div.innerHTML = '<small>[' + activity.timestamp + ']</small> <strong>' + icon + '</strong> <span style="' + (!isCurrentUser ? 'color: #0066cc;' : '') + '">' + userNameDisplay + '</span>: ' + activity.message;
                logDiv.appendChild(div);
            });
            
            logDiv.scrollTop = logDiv.scrollHeight;
        }
        
        // Enhanced addLogMessage to store activities
        function addLogMessage(message, type, userId_sender, userName_sender) {
            type = type || 'edit';
            const activityUserId = userId_sender || userId;
            const activityUserName = userName_sender || userName;
            
            const activity = {
                message: message,
                type: type,
                userId: activityUserId,
                userName: activityUserName,
                timestamp: new Date().toLocaleTimeString(),
                fullTimestamp: new Date().toISOString()
            };
            
            allActivities.push(activity);
            saveActivities();
            renderActivities();
        }
        
        // ========== FUNGSI ACTIVE USERS ==========
        
        function updateActiveUsersUI() {
            var list = document.getElementById('activeUsersList');
            list.innerHTML = '';
            
            activeUsers.forEach(function(user) {
                var li = document.createElement('li');
                var isCurrentUser = (user.id === userId);
                var displayName = isCurrentUser ? user.name + ' (You)' : user.name;
                
                li.textContent = displayName;
                if (!isCurrentUser) {
                    li.style.color = '#0066cc';
                    li.style.fontWeight = 'bold';
                    li.style.marginBottom = '5px';
                } else {
                    li.style.fontWeight = 'bold';
                    li.style.marginBottom = '5px';
                }
                
                // Add cursor position indicator if available
                if (user.cursorPosition && !isCurrentUser && user.cursorPosition > 0) {
                    var posSpan = document.createElement('span');
                    posSpan.style.fontSize = '11px';
                    posSpan.style.color = '#666';
                    posSpan.style.marginLeft = '10px';
                    posSpan.textContent = '(pos: ' + user.cursorPosition + ')';
                    li.appendChild(posSpan);
                }
                
                list.appendChild(li);
            });
            
            // Update user count
            document.getElementById('userCount').innerText = activeUsers.length;
        }
        
        // Save active users to localStorage
        function saveActiveUsers() {
            localStorage.setItem(`doc_${docId}_active_users`, JSON.stringify(activeUsers));
        }
        
        // Load active users from localStorage
        function loadActiveUsers() {
            const saved = localStorage.getItem(`doc_${docId}_active_users`);
            if (saved) {
                activeUsers = JSON.parse(saved);
                // Make sure current user is in the list
                if (!activeUsers.some(u => u.id === userId)) {
                    activeUsers.push({
                        id: userId,
                        name: userName,
                        cursorPosition: 0
                    });
                }
                updateActiveUsersUI();
            }
        }
        
        // Update user heartbeat
        function updateUserHeartbeat(senderUserId) {
            userHeartbeats[senderUserId] = Date.now();
        }
        
        // ========== WEBSOCKET (LARAVEL ECHO) ==========
        let echo = null;
        let channel = null;
        
        function initWebSocket() {
            if (typeof window.Echo !== 'undefined') {
                echo = window.Echo;
                
                channel = echo.channel('document.' + docId);
                
                channel.listen('.content.update', (event) => {
                    console.log('Content update received:', event);
                    const editor = document.getElementById('editor');
                    const cursorPos = editor.selectionStart;
                    
                    if (event.userId !== userId) {
                        editor.value = event.content;
                        lastSavedContent = event.content;
                        addLogMessage('Updated document content (Version ' + event.version + ')', 'edit', event.userId, event.userName);
                    } else {
                        addLogMessage('Saved version ' + event.version, 'save', event.userId, event.userName);
                    }
                    
                    if (event.version) {
                        currentVersion = event.version;
                        document.getElementById('versionNumber').innerText = currentVersion;
                    }
                    
                    editor.setSelectionRange(cursorPos, cursorPos);
                });
                
                channel.listen('.cursor.moved', (event) => {
                    console.log('Terima event cursor:', event);
                    if (event.userId !== userId) {
                        addLogMessage('Moved cursor to position ' + event.position, 'cursor', event.userId, event.userName);
                        
                        // Update cursor position in active users
                        const userIndex = activeUsers.findIndex(u => u.id === event.userId);
                        if (userIndex !== -1) {
                            activeUsers[userIndex].cursorPosition = event.position;
                            updateActiveUsersUI();
                            saveActiveUsers();
                        }
                    }
                });
                
                channel.listen('.user.presence', (event) => {
                    console.log('User presence:', event);
                    
                    if (event.action === 'join') {
                        if (!activeUsers.some(u => u.id === event.userId)) {
                            activeUsers.push({
                                id: event.userId,
                                name: event.userName,
                                cursorPosition: 0
                            });
                            updateActiveUsersUI();
                            addLogMessage('Joined the document', 'join', event.userId, event.userName);
                            saveActiveUsers();
                        }
                    } else if (event.action === 'leave') {
                        const leavingUser = activeUsers.find(u => u.id === event.userId);
                        if (leavingUser && leavingUser.id !== userId) {
                            activeUsers = activeUsers.filter(u => u.id !== event.userId);
                            updateActiveUsersUI();
                            addLogMessage('Left the document', 'leave', event.userId, event.userName);
                            saveActiveUsers();
                        }
                    } else if (event.action === 'heartbeat') {
                        updateUserHeartbeat(event.userId);
                    }
                });
                
                broadcastPresence('join');
                addLogMessage('✅ WebSocket connected!', 'info', userId, userName);
            } else {
                setTimeout(initWebSocket, 1000);
            }
        }
        
        function broadcastCursor(position) {
            console.log('Mengirim cursor', position);
            if (channel) {
                axios.post('/broadcast/cursor', {
                    documentId: docId, userId: userId, userName: userName, position: position
                }).catch(function(err) { console.error('Cursor error:', err); });
            }
        }
        
        function broadcastPresence(action) {
            if (channel) {
                axios.post('/broadcast/presence', {
                    documentId: docId, userId: userId, userName: userName, action: action
                }).catch(function(err) { console.error('Presence error:', err); });
            }
        }
        
        // ========== SIMULASI KONFLIK OTOMATIS ==========
        let conflictSimulationInterval = null;
        
        function startConflictSimulation() {
            if (conflictSimulationInterval) clearInterval(conflictSimulationInterval);
            
            conflictSimulationInterval = setInterval(function() {
                // Ambil semua user yang pernah terdeteksi dari localStorage
                let allKnownUsers = JSON.parse(localStorage.getItem(`doc_${docId}_all_users`) || '[]');
                
                // Filter untuk mendapatkan user selain diri sendiri
                let otherUsers = allKnownUsers.filter(function(u) { 
                    return u !== userName; 
                });
                
                if (otherUsers.length > 0) {
                    // Pilih random user lain
                    let conflictUser = otherUsers[Math.floor(Math.random() * otherUsers.length)];
                    addLogMessage('⚠️ CONFLICT DETECTED: ' + conflictUser + ' might have edited the same area!', 'conflict', 'system', 'System');
                    console.log('[CONFLICT] ' + userName + ' detected conflict from: ' + conflictUser);
                } else {
                    // Jika belum ada user lain, gunakan pesan generic
                    addLogMessage('⚠️ CONFLICT DETECTED: Another user might have edited the same area!', 'conflict', 'system', 'System');
                }
            }, 15000);
        }
        
        function stopConflictSimulation() {
            if (conflictSimulationInterval) {
                clearInterval(conflictSimulationInterval);
                conflictSimulationInterval = null;
            }
        }
        
        // Update daftar semua user ke localStorage
        function updateAllUsersList() {
            let allUsers = JSON.parse(localStorage.getItem(`doc_${docId}_all_users`) || '[]');
            
            // Tambahkan user saat ini jika belum ada
            if (!allUsers.includes(userName)) {
                allUsers.push(userName);
                localStorage.setItem(`doc_${docId}_all_users`, JSON.stringify(allUsers));
            }
            
            // Tambahkan semua active users
            activeUsers.forEach(function(u) {
                if (!allUsers.includes(u.name)) {
                    allUsers.push(u.name);
                    localStorage.setItem(`doc_${docId}_all_users`, JSON.stringify(allUsers));
                }
            });
        }
        
        // ========== FUNGSI UTAMA ==========
        
        async function saveVersion(content, isManual) {
            isManual = isManual || false;
            try {
                const response = await fetch('/documents/' + docId, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ content: content, userId: userId, userName: userName })
                });
                
                const data = await response.json();
                if (data.success) {
                    currentVersion = data.version;
                    document.getElementById('versionNumber').innerText = currentVersion;
                    // Don't add log here because it will be added from broadcast
                    return true;
                }
            } catch (error) {
                addLogMessage('❌ Failed to save: ' + error.message, 'conflict', userId, userName);
                return false;
            }
        }
        
        async function manualSave() {
            var currentContent = document.getElementById('editor').value;
            var statusSpan = document.getElementById('saveStatus');
            statusSpan.innerText = 'Saving...';
            statusSpan.style.color = 'orange';
            
            var success = await saveVersion(currentContent, true);
            if (success) {
                statusSpan.innerText = '✓ Saved!';
                statusSpan.style.color = 'green';
                lastSavedContent = currentContent;
                setTimeout(function() { statusSpan.innerText = ''; }, 2000);
            } else {
                statusSpan.innerText = '✗ Save failed!';
                statusSpan.style.color = 'red';
            }
        }
        
        function startAutoSave() {
            if (autoSaveTimer) clearInterval(autoSaveTimer);
            autoSaveTimer = setInterval(async function() {
                var currentContent = document.getElementById('editor').value;
                if (currentContent !== lastSavedContent) {
                    await saveVersion(currentContent, false);
                    lastSavedContent = currentContent;
                }
            }, 30000);
        }
        
        // Check for inactive users every minute
        setInterval(function() {
            const now = Date.now();
            let hasChanges = false;
            
            activeUsers = activeUsers.filter(function(user) {
                const lastSeen = userHeartbeats[user.id] || now;
                const isActive = (now - lastSeen) < 90000; // 90 seconds timeout
                if (!isActive && user.id !== userId) {
                    addLogMessage('Automatically removed due to inactivity', 'leave', user.id, user.name);
                    hasChanges = true;
                    return false;
                }
                return true;
            });
            
            if (hasChanges) {
                updateActiveUsersUI();
                saveActiveUsers();
            }
        }, 60000);
        
        // ========== EVENT LISTENERS ==========
        var editor = document.getElementById('editor');
        var lastContent = editor.value;
        
        editor.addEventListener('input', function(e) {
            var newContent = e.target.value;
            var diff = newContent.length - lastContent.length;
            if (diff !== 0) {
                var action = diff > 0 ? 'added ' + diff + ' chars' : 'removed ' + Math.abs(diff) + ' chars';
                addLogMessage(action, 'edit', userId, userName);
            }
            lastContent = newContent;
        });
        
        editor.addEventListener('mouseup', function() { broadcastCursor(editor.selectionStart); });
        editor.addEventListener('keyup', function() { broadcastCursor(editor.selectionStart); });
        editor.addEventListener('click', function() { broadcastCursor(editor.selectionStart); });
        
        window.addEventListener('beforeunload', function() {
            broadcastPresence('leave');
            stopConflictSimulation();
        });
        
        // ========== INITIALIZATION ==========
        function init() {
            loadActivities();
            loadActiveUsers();
            updateActiveUsersUI();
            startAutoSave();
            initWebSocket();
            updateAllUsersList();
            //startConflictSimulation(); // Uncomment if needed
            addLogMessage(userName + ' joined the document', 'info', userId, userName);
            addLogMessage('💡 Simulasi konflik akan muncul setiap 15 detik.', 'info', 'system', 'System');
            
            // Send heartbeat periodically
            setInterval(function() {
                broadcastPresence('heartbeat');
            }, 30000);
        }
        
        init();
    </script>
</body>
</html>
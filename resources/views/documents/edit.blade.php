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
            <h3 style="margin: 0 0 5px 0;">👥 Active Users (Live Cursors):</h3>
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
        
        // Active users
        let activeUsers = [userName];
        
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
                    
                    editor.value = event.content;
                    lastSavedContent = event.content;
                    
                    if (event.version) {
                        currentVersion = event.version;
                        document.getElementById('versionNumber').innerText = currentVersion;
                    }
                    
                    addLogMessage(`${event.userName} updated document content (Version ${event.version})`, 'edit');
                    editor.setSelectionRange(cursorPos, cursorPos);
                });
                
                channel.listen('.cursor.moved', (event) => {
                    if (event.userId !== userId) {
                        addLogMessage(`🖱️ ${event.userName} cursor at position ${event.position}`, 'cursor');
                    }
                });
                
                channel.listen('.user.presence', (event) => {
                    console.log('User presence:', event);
                    
                    if (event.action === 'join' && event.userName !== userName) {
                        if (!activeUsers.includes(event.userName)) {
                            activeUsers.push(event.userName);
                            updateActiveUsersUI();
                            addLogMessage(`${event.userName} joined the document`, 'info');
                            
                            // Simpan ke daftar semua user
                            let allUsers = JSON.parse(localStorage.getItem(`doc_${docId}_all_users`) || '[]');
                            if (!allUsers.includes(event.userName)) {
                                allUsers.push(event.userName);
                                localStorage.setItem(`doc_${docId}_all_users`, JSON.stringify(allUsers));
                            }
                        }
                    } else if (event.action === 'leave' && event.userName !== userName) {
                        activeUsers = activeUsers.filter(function(u) { return u !== event.userName; });
                        updateActiveUsersUI();
                        addLogMessage(`${event.userName} left the document`, 'info');
                    }
                });
                
                broadcastPresence('join');
                addLogMessage('✅ WebSocket connected!', 'info');
            } else {
                setTimeout(initWebSocket, 1000);
            }
        }
        
        function broadcastCursor(position) {
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
            
            // Update daftar user di localStorage
            if (action === 'join') {
                setTimeout(updateAllUsersList, 500);
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
                    addLogMessage(`⚠️ CONFLICT DETECTED: ${conflictUser} might have edited the same area!`, 'conflict');
                    console.log(`[CONFLICT] ${userName} detected conflict from: ${conflictUser}`);
                } else {
                    // Jika belum ada user lain, gunakan pesan generic
                    addLogMessage(`⚠️ CONFLICT DETECTED: Another user might have edited the same area!`, 'conflict');
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
                if (!allUsers.includes(u)) {
                    allUsers.push(u);
                    localStorage.setItem(`doc_${docId}_all_users`, JSON.stringify(allUsers));
                }
            });
        }
        
        // Hapus user dari daftar (opsional)
        function removeUserFromList(userToRemove) {
            let allUsers = JSON.parse(localStorage.getItem(`doc_${docId}_all_users`) || '[]');
            allUsers = allUsers.filter(function(u) { return u !== userToRemove; });
            localStorage.setItem(`doc_${docId}_all_users`, JSON.stringify(allUsers));
        }
        
        // ========== FUNGSI UTAMA ==========
        
        function updateActiveUsersUI() {
            var list = document.getElementById('activeUsersList');
            list.innerHTML = '';
            activeUsers.forEach(function(name) {
                var li = document.createElement('li');
                var isCurrentUser = (name === userName);
                var displayName = isCurrentUser ? name + ' (You)' : name;
                li.textContent = displayName;
                if (!isCurrentUser) {
                    li.style.color = '#0066cc';
                    li.style.fontWeight = 'bold';
                }
                list.appendChild(li);
            });
        }
        
        function addLogMessage(message, type) {
            type = type || 'edit';
            var logDiv = document.getElementById('conflictLog');
            var timestamp = new Date().toLocaleTimeString();
            var icon = '✏️';
            if (type === 'conflict') icon = '⚠️';
            if (type === 'cursor') icon = '🖱️';
            if (type === 'info') icon = 'ℹ️';
            if (type === 'save') icon = '💾';
            
            var div = document.createElement('div');
            div.innerHTML = '<small>[' + timestamp + ']</small> <strong>' + icon + '</strong> ' + message;
            logDiv.appendChild(div);
            logDiv.scrollTop = logDiv.scrollHeight;
            
            while (logDiv.children.length > 50) {
                logDiv.removeChild(logDiv.firstChild);
            }
        }
        
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
                    addLogMessage('💾 Version ' + currentVersion + ' saved by ' + userName + (isManual ? ' (manual)' : ' (auto-save)'), 'save');
                    return true;
                }
            } catch (error) {
                addLogMessage('❌ Failed to save: ' + error.message, 'conflict');
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
        
        // ========== EVENT LISTENERS ==========
        var editor = document.getElementById('editor');
        var lastContent = editor.value;
        
        editor.addEventListener('input', function(e) {
            var newContent = e.target.value;
            var diff = newContent.length - lastContent.length;
            if (diff !== 0) {
                var action = diff > 0 ? 'added ' + diff + ' chars' : 'removed ' + Math.abs(diff) + ' chars';
                addLogMessage(userName + ' ' + action, 'edit');
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
            updateActiveUsersUI();
            startAutoSave();
            initWebSocket();
            updateAllUsersList();
            startConflictSimulation();
            addLogMessage(userName + ' joined the document', 'info');
            addLogMessage('💡 Simulasi konflik akan muncul setiap 15 detik.', 'info');
        }
        
        init();
    </script>
</body>
</html>
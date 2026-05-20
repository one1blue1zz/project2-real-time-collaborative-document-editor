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
        let lastTypingTime = 0;  // Untuk deteksi konflik saat typing
        
        // Active users
        let activeUsers = [userName];
        let pendingContentUpdate = null;  // Untuk menyimpan update saat konflik
        
        // ========== WEBSOCKET (LARAVEL ECHO) ==========
        let echo = null;
        let channel = null;
        
        function initWebSocket() {
            if (typeof window.Echo !== 'undefined') {
                echo = window.Echo;
                
                // Join channel untuk dokumen ini
                channel = echo.channel('document.' + docId);
                
                // Listen untuk content update dengan CONFLICT DETECTION
                channel.listen('.content.update', (event) => {
                    console.log('Content update received:', event);
                    
                    // Update editor content (tanpa mengubah cursor position)
                    const editor = document.getElementById('editor');
                    const cursorPos = editor.selectionStart;
                    const currentContent = editor.value;
                    const now = Date.now();
                    
                    // DETEKSI KONFLIK: jika user sedang mengetik (dalam 2 detik terakhir)
                    const isTyping = (now - lastTypingTime) < 2000;
                    
                    if (isTyping && event.userId !== userId) {
                        // Konflik terdeteksi!
                        addLogMessage(`⚠️ CONFLICT DETECTED: ${event.userName} edited while you were typing!`, 'conflict');
                        
                        // Simpan update untuk resolusi
                        pendingContentUpdate = event;
                        
                        // Tampilkan notifikasi konflik
                        const userChoice = confirm(
                            `⚠️ KONFLIK TERDETEKSI! ⚠️\n\n` +
                            `${event.userName} menyimpan perubahan pada dokumen yang sama.\n\n` +
                            `Klik OK untuk mengambil perubahan terbaru (kehilangan perubahan Anda yang belum tersimpan).\n` +
                            `Klik CANCEL untuk mempertahankan perubahan Anda (mengabaikan perubahan ${event.userName}).\n\n` +
                            `⚠️ SARAN: Simpan pekerjaan Anda (Save as New Version) terlebih dahulu!`
                        );
                        
                        if (userChoice) {
                            // Ambil perubahan dari user lain
                            editor.value = event.content;
                            lastSavedContent = event.content;
                            addLogMessage(`Conflict resolved: Applied ${event.userName}'s changes`, 'conflict');
                            addLogMessage(`⚠️ Your unsaved changes may have been lost. Please redo your work.`, 'conflict');
                        } else {
                            // Pertahankan perubahan sendiri
                            addLogMessage(`Conflict resolved: Kept your changes, ignored ${event.userName}'s changes`, 'conflict');
                            addLogMessage(`⚠️ ${event.userName}'s changes have been discarded. They may need to redo their work.`, 'conflict');
                        }
                        
                        pendingContentUpdate = null;
                    } else if (event.userId !== userId) {
                        // Update normal dari user lain (tidak konflik)
                        editor.value = event.content;
                        lastSavedContent = event.content;
                        addLogMessage(`${event.userName} updated document content (auto-saved)`, 'edit');
                    } else if (event.userId === userId) {
                        // Update dari diri sendiri, abaikan
                        addLogMessage(`Your changes were saved (Version ${currentVersion + 1})`, 'save');
                    }
                    
                    // Update versi number
                    if (event.version) {
                        currentVersion = event.version;
                        document.getElementById('versionNumber').innerText = currentVersion;
                    }
                    
                    editor.setSelectionRange(cursorPos, cursorPos);
                });
                
                // Listen untuk cursor moved
                channel.listen('.cursor.moved', (event) => {
                    console.log('Cursor moved:', event);
                    if (event.userId !== userId) {
                        addLogMessage(`🖱️ ${event.userName} cursor at position ${event.position}`, 'cursor');
                    }
                });
                
                // Listen untuk user presence (join/leave)
                channel.listen('.user.presence', (event) => {
                    console.log('User presence:', event);
                    
                    if (event.action === 'join' && event.userName !== userName) {
                        if (!activeUsers.includes(event.userName)) {
                            activeUsers.push(event.userName);
                            updateActiveUsersUI();
                            addLogMessage(`${event.userName} joined the document`, 'info');
                        }
                    } else if (event.action === 'leave' && event.userName !== userName) {
                        activeUsers = activeUsers.filter(function(u) { return u !== event.userName; });
                        updateActiveUsersUI();
                        addLogMessage(`${event.userName} left the document`, 'info');
                    }
                });
                
                // Broadcast user join
                broadcastPresence('join');
                
                addLogMessage('✅ WebSocket connected successfully! Real-time collaboration active.', 'info');
                addLogMessage('💡 Tip: Jika ada konflik, Anda akan diminta memilih versi mana yang disimpan.', 'info');
            } else {
                console.warn('Echo not loaded yet, retrying in 1 second...');
                setTimeout(initWebSocket, 1000);
            }
        }
        
        // Broadcast cursor position
        function broadcastCursor(position) {
            if (channel) {
                axios.post('/broadcast/cursor', {
                    documentId: docId,
                    userId: userId,
                    userName: userName,
                    position: position
                }).catch(function(err) { console.error('Cursor broadcast error:', err); });
            }
        }
        
        // Broadcast user presence
        function broadcastPresence(action) {
            if (channel) {
                axios.post('/broadcast/presence', {
                    documentId: docId,
                    userId: userId,
                    userName: userName,
                    action: action
                }).catch(function(err) { console.error('Presence broadcast error:', err); });
            }
        }
        
        // ========== FUNGSI UTAMA ==========
        
        // Update active users UI
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
        
        // Add log message
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
        
        // Save version to server (dengan WebSocket broadcast)
        async function saveVersion(content, isManual) {
            isManual = isManual || false;
            try {
                const response = await fetch('/documents/' + docId, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ 
                        content: content,
                        userId: userId,
                        userName: userName
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    currentVersion = data.version;
                    document.getElementById('versionNumber').innerText = currentVersion;
                    addLogMessage('💾 Version ' + currentVersion + ' saved by ' + userName + (isManual ? ' (manual)' : ' (auto-save)'), 'save');
                    return true;
                }
            } catch (error) {
                console.error('Save error:', error);
                addLogMessage('❌ Failed to save: ' + error.message, 'conflict');
                return false;
            }
        }
        
        // Manual save
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
        
        // Auto-save
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
        
        // Track local edits
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
        
        // Track typing time untuk deteksi konflik
        editor.addEventListener('keydown', function() {
            lastTypingTime = Date.now();
        });
        
        // Track cursor position
        editor.addEventListener('mouseup', function() { broadcastCursor(editor.selectionStart); });
        editor.addEventListener('keyup', function() { broadcastCursor(editor.selectionStart); });
        editor.addEventListener('click', function() { broadcastCursor(editor.selectionStart); });
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            broadcastPresence('leave');
        });
        
        // ========== INITIALIZATION ==========
        function init() {
            updateActiveUsersUI();
            startAutoSave();
            initWebSocket();
            addLogMessage(userName + ' joined the document', 'info');
        }
        
        init();
    </script>
</body>
</html>
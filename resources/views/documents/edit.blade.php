<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Document - Collaborative Editor</title>
    {{-- No CSS as requested --}}
</head>
<body>

<h1>Editing: <span id="docTitle">{{ $document->title ?? 'Untitled' }}</span></h1>

<div>
    <strong>Document ID:</strong> {{ $document->id ?? 'N/A' }}
    <br>
    <strong>Current version:</strong> <span id="versionNumber">{{ $document->version ?? 1 }}</span>
    <a href="{{ route('documents.history', $document->id) }}" target="_blank">View Version History</a>
</div>

<hr>

<div>
    <h3>Active users (live cursors):</h3>
    <ul id="activeUsersList">
        <li>You ({{ session('user_name') ?? 'Anonymous' }})</li>
    </ul>
</div>

<hr>

<div>
    <label for="editor">Document content:</label><br>
    <textarea id="editor" rows="20" cols="80" placeholder="Start typing...">{{ $document->content ?? '' }}</textarea>
</div>

<div>
    <h4>Conflict / edit log (who edited what):</h4>
    <div id="conflictLog" style="border:1px solid #ccc; padding:8px; max-height:150px; overflow-y:auto;">
        {{-- Conflict and edit messages appear here --}}
    </div>
</div>

<script>
    // Simulated collaborative editing with localStorage (for demo)
    // In real scenario, replace with WebSocket (Pusher, Laravel Echo, Socket.io)
    const docId = Number("{{ $document->id ?? 1 }}");
    let currentVersion = Number("{{ $document->version ?? 1 }}");

    // Store active users (simulate presence)
    let activeUsers = [userId];
    const userName = prompt("Enter your name for collaboration:", "User_" + userId.substr(-4)) || "Anonymous";
    
    // Update active users list
    function updateActiveUsersUI() {
        const list = document.getElementById('activeUsersList');
        list.innerHTML = '';
        activeUsers.forEach(uid => {
            const li = document.createElement('li');
            li.textContent = uid === userId ? `${userName} (You)` : uid;
            list.appendChild(li);
        });
    }

    // Simulate other users joining (for demo only)
    function randomOtherUser() {
        const fakeUsers = ['Alice', 'Bob', 'Charlie'];
        const randomName = fakeUsers[Math.floor(Math.random() * fakeUsers.length)] + '_' + Math.random().toString(36).substr(2, 4);
        if (!activeUsers.includes(randomName) && randomName !== userId) {
            activeUsers.push(randomName);
            updateActiveUsersUI();
            addConflictMessage(`System: ${randomName} joined the document`, 'info');
        }
    }
    setInterval(randomOtherUser, 15000); // simulates joining every 15 sec

    // Log conflicts & edits (who edited what)
    function addConflictMessage(message, type = 'edit') {
        const logDiv = document.getElementById('conflictLog');
        const timestamp = new Date().toLocaleTimeString();
        const prefix = type === 'conflict' ? '⚠️ CONFLICT:' : (type === 'info' ? 'ℹ️' : '✏️ EDIT:');
        const p = document.createElement('div');
        p.innerHTML = `<small>[${timestamp}]</small> <strong>${prefix}</strong> ${message}`;
        logDiv.appendChild(p);
        logDiv.scrollTop = logDiv.scrollHeight;
    }

    // Version history simulation (save version)
    function saveVersion(content, reason = 'auto-save') {
        currentVersion++;
        document.getElementById('versionNumber').innerText = currentVersion;
        // In real app: POST /documents/version with content
        addConflictMessage(`Version ${currentVersion} created (${reason}) by ${userName}`, 'info');
        // Store in localStorage for demo
        localStorage.setItem(`doc_${docId}_v${currentVersion}`, content);
        localStorage.setItem(`doc_${docId}_currentVersion`, currentVersion);
    }

    // Conflict resolution: detect if content changed from underneath
    let lastKnownContent = document.getElementById('editor').value;
    let conflictResolved = false;

    // Simulate remote changes (to test conflict detection)
    function simulateRemoteEdit() {
        const editor = document.getElementById('editor');
        const remoteContent = editor.value + "\n\n[Remote edit by " + (activeUsers.find(u => u !== userId) || "Someone") + "]";
        if (editor.value !== remoteContent && !conflictResolved) {
            const userChoice = confirm("Conflict detected! Another user edited while you were typing.\n\nYour version is shown. Click OK to merge (accept remote changes at bottom), Cancel to keep yours.");
            if (userChoice) {
                // Merge: keep current + append remote (simple resolution)
                const merged = editor.value + "\n\n--- Merged from remote ---\n" + remoteContent.split("\n\n[Remote edit")[1];
                editor.value = merged;
                addConflictMessage(`Conflict resolved by ${userName}: merged remote changes`, 'conflict');
            } else {
                addConflictMessage(`Conflict resolved by ${userName}: kept own changes, remote discarded`, 'conflict');
            }
            conflictResolved = true;
            setTimeout(() => { conflictResolved = false; }, 3000);
        } else if (remoteContent !== editor.value && !conflictResolved) {
            editor.value = remoteContent;
            addConflictMessage(`Remote change applied from ${activeUsers.find(u => u !== userId) || 'another user'}`, 'edit');
        }
    }
    setInterval(simulateRemoteEdit, 20000);

    // Track cursor position (live cursor tracking)
    const editor = document.getElementById('editor');
    editor.addEventListener('mouseup', () => {
        const cursorPos = editor.selectionStart;
        // Broadcast cursor position to other users (simulate via localStorage)
        localStorage.setItem(`cursor_${docId}_${userId}`, JSON.stringify({
            user: userName,
            pos: cursorPos,
            time: Date.now()
        }));
        addConflictMessage(`${userName} moved cursor to position ${cursorPos}`, 'info');
    });

    // Simulate receiving other users' cursor positions
    function listenOtherCursors() {
        // For demo, just show other cursors in log
        const storageEvent = (e) => {
            if (e.key && e.key.startsWith(`cursor_${docId}_`) && !e.key.includes(userId)) {
                const data = JSON.parse(e.newValue);
                if (data) {
                    addConflictMessage(`🖱️ ${data.user} is at position ${data.pos}`, 'info');
                }
            }
        };
        window.addEventListener('storage', storageEvent);
        // Also poll for simplicity
        setInterval(() => {
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith(`cursor_${docId}_`) && !key.includes(userId)) {
                    const data = JSON.parse(localStorage.getItem(key));
                    if (data && (Date.now() - data.time) < 5000) {
                        // cursor still active
                    }
                }
            }
        }, 5000);
    }
    listenOtherCursors();

    // Auto-save version every 30 seconds (version history)
    let autoSaveInterval = setInterval(() => {
        const currentContent = editor.value;
        if (currentContent !== lastKnownContent) {
            saveVersion(currentContent, 'auto-save');
            lastKnownContent = currentContent;
        }
    }, 30000);

    // Manual version save (optional)
    window.saveNow = () => {
        saveVersion(editor.value, 'manual save');
        lastKnownContent = editor.value;
    };

    // Track edits for conflict log (who edited what)
    editor.addEventListener('input', (e) => {
        const newContent = e.target.value;
        const diff = newContent.length - lastKnownContent.length;
        if (diff !== 0) {
            addConflictMessage(`${userName} edited document (${diff > 0 ? 'added' : 'removed'} ${Math.abs(diff)} chars)`, 'edit');
        }
        lastKnownContent = newContent;
        
        // Simulate saving a mini version on every significant change (for history)
        if (Math.random() < 0.1) { // 10% chance per edit to trigger version bump for demo
            saveVersion(newContent, 'edit-trigger');
        }
    });

    // Initial load: show remote cursor simulation note
    addConflictMessage(`Started editing as ${userName}. Other users may join.`, 'info');
    updateActiveUsersUI();
    
    // Set initial lastKnownContent
    lastKnownContent = editor.value;

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        // Remove cursor from storage
        localStorage.removeItem(`cursor_${docId}_${userId}`);
        // Remove user from active users (in real app broadcast)
        addConflictMessage(`${userName} left the document`, 'info');
    });
</script>

<br>
<button onclick="saveNow()" style="margin-top:10px;">📀 Save as new version (manual)</button>
<p><small>Note: Version history is stored in browser localStorage for demo. Real app would use DB + WebSockets.</small></p>

</body>
</html>
<?php
// =========================================================================
// CONFIGURACIÓN DE CLAVE API
// =========================================================================
if (!defined('YOUTUBE_API_KEY')) {
    define('YOUTUBE_API_KEY', 'AIzaSyD3ZReSXjU46YTwdMmJncwuG0t2efZsqY8'); 
}

/**
 * Función optimizada para buscar videos en YouTube
 */
function buscarVideosYouTube($query, $maxResults = 30) {
    $apiKey = YOUTUBE_API_KEY;
    $queryWithExclusion = $query . ' -"LatinAutor"';
    $queryEncoded = urlencode($queryWithExclusion);
    
    $url = "https://www.googleapis.com/youtube/v3/search?"
         . "part=snippet"
         . "&type=video"
         . "&videoEmbeddable=true"
         . "&maxResults={$maxResults}"
         . "&q={$queryEncoded}"
         . "&key={$apiKey}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => 'Error al conectar con YouTube API (Código: ' . $httpCode . ')', 'items' => []];
    }
    
    return json_decode($response, true);
}

// =========================================================================
// MANEJO DE SESIÓN Y PLAYLIST (AJAX)
// =========================================================================
session_start();

// Inicializar playlist si no existe
if (!isset($_SESSION['playlist'])) {
    $_SESSION['playlist'] = [];
}

// Manejar peticiones AJAX
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'search_videos':
            $query = isset($_POST['query']) ? trim($_POST['query']) : '';
            
            if (empty($query)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Búsqueda vacía'
                ]);
                exit;
            }
            
            $data = buscarVideosYouTube($query);
            $resultados = [];
            
            if (isset($data['error'])) {
                echo json_encode([
                    'success' => false,
                    'error' => $data['error']
                ]);
                exit;
            }
            
            if (isset($data['items']) && !empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    if (!isset($item['id']['videoId'])) continue;
                    
                    $videoId = $item['id']['videoId'];
                    $resultados[] = [
                        'id' => $videoId,
                        'title' => $item['snippet']['title'],
                        'channel' => $item['snippet']['channelTitle'],
                        'description' => isset($item['snippet']['description']) ? $item['snippet']['description'] : '',
                        'thumbnail' => isset($item['snippet']['thumbnails']['medium']['url']) 
                            ? $item['snippet']['thumbnails']['medium']['url'] 
                            : '',
                        'inPlaylist' => isset($_SESSION['playlist'][$videoId])
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'resultados' => $resultados,
                'query' => $query
            ]);
            exit;
            
        case 'add_to_playlist':
            $videoData = json_decode($_POST['video_data'], true);
            $videoId = $videoData['id'];
            
            if (!isset($_SESSION['playlist'][$videoId])) {
                $_SESSION['playlist'][$videoId] = $videoData;
                echo json_encode([
                    'success' => true,
                    'message' => 'Canción agregada',
                    'playlistCount' => count($_SESSION['playlist']),
                    'playlist' => array_values($_SESSION['playlist'])
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'La canción ya está en la lista',
                    'playlistCount' => count($_SESSION['playlist']),
                    'playlist' => array_values($_SESSION['playlist'])
                ]);
            }
            exit;
            
        case 'remove_from_playlist':
            $videoId = $_POST['video_id'];
            unset($_SESSION['playlist'][$videoId]);
            echo json_encode([
                'success' => true,
                'message' => 'Canción eliminada',
                'playlistCount' => count($_SESSION['playlist']),
                'playlist' => array_values($_SESSION['playlist'])
            ]);
            exit;
            
        case 'clear_playlist':
            $_SESSION['playlist'] = [];
            echo json_encode([
                'success' => true,
                'message' => 'Lista limpiada',
                'playlistCount' => 0,
                'playlist' => []
            ]);
            exit;
            
        case 'get_playlist':
            echo json_encode([
                'success' => true,
                'playlistCount' => count($_SESSION['playlist']),
                'playlist' => array_values($_SESSION['playlist'])
            ]);
            exit;
    }
}

$playlistCount = count($_SESSION['playlist']);
$playlistArray = array_values($_SESSION['playlist']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>desestes365 Buscador</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
                <div class="search-form">
                    <form id="searchForm" onsubmit="searchVideos(event)">
                        <img src="logo.png" alt="Logo" width="50" height="45" >
                        <input 
                            type="text" 
                            id="searchInput"
                            class="search-input"
                            placeholder="Buscar videos en YouTube..." 
                            required
                        >
                        <button type="submit" class="search-btn" id="searchBtn">Buscar</button>
                    </form>
                </div>
    </div>
    <div class="container">
        

        <div class="main-content">
            <!-- SECCIÓN DE BÚSQUEDA -->

        <!-- REPRODUCTOR PRINCIPAL CON PUBLICIDAD -->
        <div class="player-section">
           
            
          





            <div class="search-section">

              <div class="player-with-ads">
                <!-- Reproductor de Video -->
                <div id="playerContainer">
                    <div class="empty-player">
                        <div class="empty-player-content">
                            <h3>🎵 Sin reproducción</h3>
                            <p>Selecciona canciones y presiona "Reproducir Lista"</p>
                        </div>
                    </div>
                </div>
            </div>
        

         <div class="player-header">
                <div class="player-info">
                    <div class="player-title" id="currentTitle">Reproductor de Lista</div>
                    <div class="player-subtitle" id="currentSubtitle">
                        <?php echo $playlistCount > 0 ? "Lista con {$playlistCount} canciones" : "Agrega canciones para comenzar"; ?>
                    </div>
                </div>
                <div class="player-controls">
                    <button class="control-btn nav-btn" id="prevBtn" onclick="playPrevious()" disabled>⏮ Anterior</button>
                    <button class="control-btn play-btn" id="playBtn" onclick="startPlaylist()" <?php echo $playlistCount == 0 ? 'disabled' : ''; ?>>
                        ▶ Reproducir Lista
                    </button>
                    <button class="control-btn nav-btn" id="nextBtn" onclick="playNext()" disabled>Siguiente ⏭</button>
                </div>
            </div>
        </div>
                               
                
                <div id="searchMessage" style="display: none;"></div>
                
                <div id="searchResults">
                    <div class="search-placeholder">
                        <h2>🔍 Busca tu música favorita</h2>
                        <p>Escribe el nombre de una canción, artista o álbum</p>
                    </div>
                </div>
            </div>
            
            <!-- PLAYLIST SIDEBAR -->
            <div class="playlist-sidebar">
                <div class="playlist-header">
                    <span class="playlist-title">Mi Lista</span>
                    <span class="playlist-count" id="playlistCountBadge"><?php echo $playlistCount; ?></span>
                </div>
                
                <?php if ($playlistCount > 0): ?>
                    <button type="button" class="clear-btn" onclick="clearPlaylist()">
                        🗑️ Limpiar lista
                    </button>
                <?php endif; ?>
                
                <div class="playlist-items" id="playlistItems">
                    <?php if ($playlistCount > 0): ?>
                        <?php $index = 0; foreach ($_SESSION['playlist'] as $video): ?>
                            <div class="playlist-item" data-index="<?php echo $index; ?>" onclick="playFromPlaylist(<?php echo $index; ?>)">
                                <img src="<?php echo htmlspecialchars($video['thumbnail'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="<?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="playlist-item-info">
                                    <div class="playlist-item-title">
                                        <?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <div class="playlist-item-channel">
                                        <?php echo htmlspecialchars($video['channel'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </div>
                                <button 
                                    type="button" 
                                    class="remove-btn" 
                                    data-video-id="<?php echo htmlspecialchars($video['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                    onclick="event.stopPropagation(); removeFromPlaylist(this);">
                                    ✕
                                </button>
                            </div>
                        <?php $index++; endforeach; ?>
                    <?php else: ?>
                        <div class="empty-playlist">
                            <p>🎵 Tu lista está vacía</p>
                            <p style="font-size: 13px; margin-top: 10px;">Busca y agrega canciones para empezar</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Playlist de videos
        let playlist = <?php echo json_encode($playlistArray); ?>;
        let currentIndex = 0;
        let player = null;
        let isPlaying = false;
        
        // Cargar API de YouTube IFrame
        const tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        const firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
        
        // Función llamada cuando la API está lista
        function onYouTubeIframeAPIReady() {
            console.log('YouTube API lista');
        }
        
        // ===================================================================
        // BÚSQUEDA DE VIDEOS (AJAX)
        // ===================================================================
        
        async function searchVideos(event) {
            event.preventDefault();
            
            const searchInput = document.getElementById('searchInput');
            const searchBtn = document.getElementById('searchBtn');
            const searchMessage = document.getElementById('searchMessage');
            const searchResults = document.getElementById('searchResults');
            const query = searchInput.value.trim();
            
            if (!query) return;
            
            // Mostrar estado de carga
            searchBtn.disabled = true;
            searchBtn.textContent = 'Buscando...';
            searchMessage.style.display = 'none';
            
            try {
                const formData = new FormData();
                formData.append('ajax_action', 'search_videos');
                formData.append('query', query);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    displaySearchResults(result.resultados, result.query);
                } else {
                    searchMessage.className = 'error-message';
                    searchMessage.textContent = result.error || 'Error en la búsqueda';
                    searchMessage.style.display = 'block';
                    searchResults.innerHTML = '';
                }
            } catch (error) {
                console.error('Error en la búsqueda:', error);
                searchMessage.className = 'error-message';
                searchMessage.textContent = 'Error al realizar la búsqueda. Intenta nuevamente.';
                searchMessage.style.display = 'block';
            } finally {
                searchBtn.disabled = false;
                searchBtn.textContent = 'Buscar';
            }
        }
        
        function displaySearchResults(resultados, query) {
            const searchResults = document.getElementById('searchResults');
            
            if (resultados.length === 0) {
                searchResults.innerHTML = `
                    <div class="no-results">
                        <p>😕 No se encontraron videos para "${escapeHtml(query)}"</p>
                        <p>Intenta con otros términos de búsqueda.</p>
                    </div>
                `;
                return;
            }
            
            let html = `
                <div class="results-header">
                    <h2>Resultados para: "${escapeHtml(query)}"</h2>
                    <p class="results-count">${resultados.length} videos disponibles</p>
                </div>
                <div class="video-grid">
            `;
            
            resultados.forEach(video => {
                const buttonClass = video.inPlaylist ? 'add-btn added' : 'add-btn';
                const buttonText = video.inPlaylist ? '✓ Agregado' : '+ Agregar a lista';
                
                html += `
                    <div class="video-card">
                        <div class="video-thumbnail">
                            <img src="${escapeHtml(video.thumbnail)}" alt="${escapeHtml(video.title)}">
                        </div>
                        <div class="video-info">
                            <h3 class="video-title">${escapeHtml(video.title)}</h3>
                            <p class="video-channel">${escapeHtml(video.channel)}</p>
                            <button 
                                type="button" 
                                class="${buttonClass}"
                                data-video='${escapeHtml(JSON.stringify(video))}'
                                onclick="addToPlaylist(this)">
                                ${buttonText}
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            searchResults.innerHTML = html;
        }
        
        // ===================================================================
        // FUNCIONES AJAX PARA GESTIÓN DE PLAYLIST
        // ===================================================================
        
        async function addToPlaylist(button) {
            const videoData = JSON.parse(button.getAttribute('data-video'));
            
            try {
                const formData = new FormData();
                formData.append('ajax_action', 'add_to_playlist');
                formData.append('video_data', JSON.stringify(videoData));
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    button.classList.add('added');
                    button.textContent = '✓ Agregado';
                    playlist = result.playlist;
                    updatePlaylistUI(result.playlist, result.playlistCount);
                }
            } catch (error) {
                console.error('Error al agregar a la lista:', error);
                alert('Error al agregar la canción. Intenta nuevamente.');
            }
        }
        
        async function removeFromPlaylist(button) {
            const videoId = button.getAttribute('data-video-id');
            
            try {
                const formData = new FormData();
                formData.append('ajax_action', 'remove_from_playlist');
                formData.append('video_id', videoId);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    playlist = result.playlist;
                    
                    // Si se eliminó la canción actual, detener reproducción
                    if (isPlaying && playlist[currentIndex]?.id === videoId) {
                        if (player) {
                            player.stopVideo();
                        }
                        isPlaying = false;
                        currentIndex = 0;
                    } else if (currentIndex >= playlist.length && playlist.length > 0) {
                        currentIndex = playlist.length - 1;
                    }
                    
                    updatePlaylistUI(result.playlist, result.playlistCount);
                    
                    // Actualizar botones de agregar en resultados de búsqueda
                    const addButtons = document.querySelectorAll('.add-btn');
                    addButtons.forEach(btn => {
                        const btnVideo = JSON.parse(btn.getAttribute('data-video'));
                        if (btnVideo.id === videoId) {
                            btn.classList.remove('added');
                            btn.textContent = '+ Agregar a lista';
                        }
                    });
                }
            } catch (error) {
                console.error('Error al eliminar de la lista:', error);
                alert('Error al eliminar la canción. Intenta nuevamente.');
            }
        }
        
        async function clearPlaylist() {
            if (!confirm('¿Seguro que deseas limpiar toda la lista?')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('ajax_action', 'clear_playlist');
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    playlist = [];
                    currentIndex = 0;
                    
                    if (player) {
                        player.stopVideo();
                    }
                    isPlaying = false;
                    
                    updatePlaylistUI([], 0);
                    
                    // Resetear reproductor
                    const playerContainer = document.getElementById('playerContainer');
                    playerContainer.innerHTML = `
                        <div class="empty-player">
                            <div class="empty-player-content">
                                <h3>🎵 Sin reproducción</h3>
                                <p>Selecciona canciones y presiona "Reproducir Lista"</p>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('currentTitle').textContent = 'Reproductor de Lista';
                    document.getElementById('currentSubtitle').textContent = 'Agrega canciones para comenzar';
                    document.getElementById('playBtn').disabled = true;
                    document.getElementById('playBtn').textContent = '▶ Reproducir Lista';
                    
                    // Actualizar todos los botones de agregar
                    const addButtons = document.querySelectorAll('.add-btn');
                    addButtons.forEach(btn => {
                        btn.classList.remove('added');
                        btn.textContent = '+ Agregar a lista';
                    });
                }
            } catch (error) {
                console.error('Error al limpiar la lista:', error);
                alert('Error al limpiar la lista. Intenta nuevamente.');
            }
        }
        
        function updatePlaylistUI(playlistData, count) {
            const playlistItems = document.getElementById('playlistItems');
            const playlistCountBadge = document.getElementById('playlistCountBadge');
            const playBtn = document.getElementById('playBtn');
            const currentSubtitle = document.getElementById('currentSubtitle');
            
            // Actualizar contador
            playlistCountBadge.textContent = count;
            
            // Actualizar subtítulo del reproductor
            if (count > 0 && !isPlaying) {
                currentSubtitle.textContent = `Lista con ${count} canciones`;
            } else if (isPlaying && playlist[currentIndex]) {
                currentSubtitle.textContent = `${playlist[currentIndex].channel} • ${currentIndex + 1} de ${count}`;
            } else {
                currentSubtitle.textContent = 'Agrega canciones para comenzar';
            }
            
            // Habilitar/deshabilitar botón de reproducir
            playBtn.disabled = count === 0;
            
            // Actualizar HTML de la lista
            if (count === 0) {
                playlistItems.innerHTML = `
                    <div class="empty-playlist">
                        <p>🎵 Tu lista está vacía</p>
                        <p style="font-size: 13px; margin-top: 10px;">Busca y agrega canciones para empezar</p>
                    </div>
                `;
            } else {
                let html = '';
                playlistData.forEach((video, index) => {
                    const isCurrentlyPlaying = isPlaying && index === currentIndex ? 'playing' : '';
                    html += `
                        <div class="playlist-item ${isCurrentlyPlaying}" data-index="${index}" onclick="playFromPlaylist(${index})">
                            <img src="${escapeHtml(video.thumbnail)}" alt="${escapeHtml(video.title)}">
                            <div class="playlist-item-info">
                                <div class="playlist-item-title">${escapeHtml(video.title)}</div>
                                <div class="playlist-item-channel">${escapeHtml(video.channel)}</div>
                            </div>
                            <button 
                                type="button" 
                                class="remove-btn" 
                                data-video-id="${escapeHtml(video.id)}"
                                onclick="event.stopPropagation(); removeFromPlaylist(this);">
                                ✕
                            </button>
                        </div>
                    `;
                });
                playlistItems.innerHTML = html;
                
                // Mostrar/ocultar botón de limpiar
                const existingClearBtn = document.querySelector('.clear-btn');
                if (existingClearBtn) {
                    existingClearBtn.style.display = 'block';
                } else {
                    const clearBtn = document.createElement('button');
                    clearBtn.type = 'button';
                    clearBtn.className = 'clear-btn';
                    clearBtn.onclick = clearPlaylist;
                    clearBtn.textContent = '🗑️ Limpiar lista';
                    document.querySelector('.playlist-header').after(clearBtn);
                }
            }
        }
        
        function escapeHtml(text) {
            if (typeof text !== 'string') {
                text = String(text);
            }
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // ===================================================================
        // FUNCIONES DE REPRODUCCIÓN
        // ===================================================================
        
        // Iniciar reproducción de la lista
        function startPlaylist() {
            if (playlist.length === 0) return;
            currentIndex = 0;
            playVideo(currentIndex);
        }
        
        // Reproducir video específico
        function playVideo(index) {
            if (index < 0 || index >= playlist.length) return;
            
            currentIndex = index;
            const video = playlist[currentIndex];
            
            // Actualizar info del reproductor
            document.getElementById('currentTitle').textContent = video.title;
            document.getElementById('currentSubtitle').textContent = `${video.channel} • ${currentIndex + 1} de ${playlist.length}`;
            
            // Actualizar estados visuales
            updatePlaylistHighlight();
            updateNavigationButtons();
            
            // Crear/actualizar reproductor
            const playerContainer = document.getElementById('playerContainer');
            
            if (player) {
                player.loadVideoById(video.id);
            } else {
                playerContainer.innerHTML = '<div class="current-player"><div id="ytPlayer"></div></div>';
                
                player = new YT.Player('ytPlayer', {
                    videoId: video.id,
                    playerVars: {
                        autoplay: 1,
                        controls: 1,
                        rel: 0,
                        modestbranding: 1
                    },
                    events: {
                        'onStateChange': onPlayerStateChange
                    }
                });
            }
            
            isPlaying = true;
            document.getElementById('playBtn').textContent = '⏸ Pausar';
        }
        
        // Manejar cambios de estado del reproductor
        function onPlayerStateChange(event) {
            if (event.data === YT.PlayerState.ENDED) {
                playNext();
            }
        }
        
        // Reproducir siguiente
        function playNext() {
            if (currentIndex < playlist.length - 1) {
                playVideo(currentIndex + 1);
            } else {
                playVideo(0);
            }
        }
        
        // Reproducir anterior
        function playPrevious() {
            if (currentIndex > 0) {
                playVideo(currentIndex - 1);
            }
        }
        
        // Reproducir desde la lista
        function playFromPlaylist(index) {
            playVideo(index);
        }
        
        // Actualizar resaltado en la lista
        function updatePlaylistHighlight() {
            const items = document.querySelectorAll('.playlist-item');
            items.forEach((item, idx) => {
                if (idx === currentIndex) {
                    item.classList.add('playing');
                } else {
                    item.classList.remove('playing');
                }
            });
        }
        
        // Actualizar botones de navegación
        function updateNavigationButtons() {
            document.getElementById('prevBtn').disabled = currentIndex === 0;
            document.getElementById('nextBtn').disabled = currentIndex === playlist.length - 1;
        }
        
        // Botón play/pause
        document.getElementById('playBtn').addEventListener('click', function() {
            if (!isPlaying) {
                startPlaylist();
            } else if (player) {
                const state = player.getPlayerState();
                if (state === YT.PlayerState.PLAYING) {
                    player.pauseVideo();
                    this.textContent = '▶ Reproducir';
                } else {
                    player.playVideo();
                    this.textContent = '⏸ Pausar';
                }
            }
        });
    </script>
</body>
</html>
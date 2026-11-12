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
// MANEJO DE SESIÓN Y PLAYLIST
// =========================================================================
session_start();

// Inicializar playlist si no existe
if (!isset($_SESSION['playlist'])) {
    $_SESSION['playlist'] = [];
}

// Agregar canción a la playlist
if (isset($_POST['add_to_playlist'])) {
    $videoData = json_decode($_POST['video_data'], true);
    $videoId = $videoData['id'];
    
    // Evitar duplicados
    if (!isset($_SESSION['playlist'][$videoId])) {
        $_SESSION['playlist'][$videoId] = $videoData;
    }
}

// Eliminar canción de la playlist
if (isset($_POST['remove_from_playlist'])) {
    $videoId = $_POST['video_id'];
    unset($_SESSION['playlist'][$videoId]);
}

// Limpiar toda la playlist
if (isset($_POST['clear_playlist'])) {
    $_SESSION['playlist'] = [];
}

// =========================================================================
// PROCESAMIENTO DE BÚSQUEDA
// =========================================================================

$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$resultados = [];
$error = null;

if (!empty($busqueda)) {
    $data = buscarVideosYouTube($busqueda);
    
    if (isset($data['error'])) {
        $error = $data['error'];
    } elseif (isset($data['items']) && !empty($data['items'])) {
        foreach ($data['items'] as $item) {
            if (!isset($item['id']['videoId'])) continue;
            
            $resultados[] = [
                'id' => $item['id']['videoId'],
                'title' => $item['snippet']['title'],
                'channel' => $item['snippet']['channelTitle'],
                'description' => isset($item['snippet']['description']) ? $item['snippet']['description'] : '',
                'thumbnail' => isset($item['snippet']['thumbnails']['medium']['url']) 
                    ? $item['snippet']['thumbnails']['medium']['url'] 
                    : ''
            ];
        }
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9f9f9;
            padding: 20px;
        }
        
        .container {
             width: 70vw;     /* 70% del ancho de la pantalla */
              height: 40vh;    /* 40% del alto de la pantalla */
            /*max-width: 1400px;*/
            margin: 0 auto;
        }
        
        h1 {
            color: #ff0000;
            margin-bottom: 20px;
            font-size: 2em;
        }
        
        /* REPRODUCTOR PRINCIPAL */
        .player-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .player-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .player-info {
            flex: 1;
        }
        
        .player-title {
            font-size: 18px;
            font-weight: 700;
            color: #030303;
            margin-bottom: 5px;
        }
        
        .player-subtitle {
            font-size: 14px;
            color: #606060;
        }
        
        .player-controls {
            display: flex;
            gap: 10px;
        }
        
        .control-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .play-btn {
            background-color: #ff0000;
            color: white;
        }
        
        .play-btn:hover {
            background-color: #cc0000;
        }
        
        .play-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        .nav-btn {
            background-color: #065fd4;
            color: white;
        }
        
        .nav-btn:hover {
            background-color: #0047a3;
        }
        
        .nav-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        .current-player {
            position: relative;
            padding-bottom: 56.25%;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .current-player iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .empty-player {
            position: relative;
            padding-bottom: 46.25%;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .empty-player-content {
            position: absolute;
            text-align: center;
            color: white;
        }
        
        .empty-player-content h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .empty-player-content p {
            font-size: 16px;
            opacity: 0.8;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
        }
        
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            .playlist-sidebar {
                order: -1;
            }
        }
        
        .search-section {
            min-width: 0;
        }
        
        .search-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .search-input {
            width: 70%;
            padding: 12px;
            font-size: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            outline: none;
        }
        
        .search-input:focus {
            border-color: #ff0000;
        }
        
        .search-btn {
            padding: 12px 30px;
            font-size: 16px;
            background-color: #ff0000;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .search-btn:hover {
            background-color: #cc0000;
        }
        
        .results-header {
            margin-bottom: 20px;
        }
        
        .results-count {
            color: #606060;
            font-size: 14px;
        }
        
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .video-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .video-thumbnail {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%;
            background: #000;
            overflow: hidden;
        }
        
        .video-thumbnail img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .video-info {
            padding: 12px;
        }
        
        .video-title {
            font-size: 14px;
            font-weight: 600;
            color: #030303;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .video-channel {
            font-size: 13px;
            color: #606060;
            margin-bottom: 10px;
        }
        
        .add-btn {
            width: 100%;
            padding: 8px;
            background-color: #065fd4;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        
        .add-btn:hover {
            background-color: #0047a3;
        }
        
        .add-btn.added {
            background-color: #00a152;
        }
        
        .add-btn.added:hover {
            background-color: #008c45;
        }
        
        /* PLAYLIST SIDEBAR */
        .playlist-sidebar {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 40px);
            display: flex;
            flex-direction: column;
        }
        
        .playlist-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .playlist-title {
            font-size: 18px;
            font-weight: 700;
            color: #030303;
        }
        
        .playlist-count {
            background: #ff0000;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .clear-btn {
            padding: 8px 16px;
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        .clear-btn:hover {
            background-color: #d32f2f;
        }
        
        .playlist-items {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }
        
        .playlist-item {
            display: flex;
            gap: 10px;
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .playlist-item:hover {
            background: #f5f5f5;
        }
        
        .playlist-item.playing {
            background: #e3f2fd;
            border-left: 4px solid #065fd4;
        }
        
        .playlist-item img {
            width: 80px;
            height: 45px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .playlist-item-info {
            flex: 1;
            min-width: 0;
        }
        
        .playlist-item-title {
            font-size: 13px;
            font-weight: 600;
            color: #030303;
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .playlist-item-channel {
            font-size: 12px;
            color: #606060;
        }
        
        .remove-btn {
            padding: 4px 8px;
            background-color: #ff4444;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            align-self: center;
        }
        
        .remove-btn:hover {
            background-color: #cc0000;
        }
        
        .empty-playlist {
            text-align: center;
            padding: 40px 20px;
            color: #606060;
        }
        
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            color: #606060;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎥 Desestrézate los 365 días del año.</h1>
        
        <!-- REPRODUCTOR PRINCIPAL -->
        <div class="player-section">
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
            
            <div id="playerContainer">
                <div class="empty-player">
                    <div class="empty-player-content">
                        <h3>🎵 Sin reproducción</h3>
                        <p>Selecciona canciones y presiona "Reproducir Lista"</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="main-content">
            <!-- SECCIÓN DE BÚSQUEDA -->
            <div class="search-section">
                <div class="search-form">
                    <form method="get" action="">
                        <input 
                            type="text" 
                            name="q" 
                            class="search-input"
                            placeholder="Buscar videos en YouTube..." 
                            value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                        <button type="submit" class="search-btn">Buscar</button>
                    </form>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message">
                        ⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($busqueda)): ?>
                    <div class="results-header">
                        <h2>Resultados para: "<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>"</h2>
                        <p class="results-count">
                            <?php echo count($resultados); ?> videos disponibles
                        </p>
                    </div>
                    
                    <?php if (!empty($resultados)): ?>
                        <div class="video-grid">
                            <?php foreach ($resultados as $video): ?>
                                <div class="video-card">
                                    <div class="video-thumbnail">
                                        <img src="<?php echo htmlspecialchars($video['thumbnail'], ENT_QUOTES, 'UTF-8'); ?>" 
                                             alt="<?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="video-info">
                                        <h3 class="video-title">
                                            <?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?>
                                        </h3>
                                        <p class="video-channel">
                                            <?php echo htmlspecialchars($video['channel'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <form method="post" style="margin: 0;">
                                            <input type="hidden" name="video_data" value='<?php echo htmlspecialchars(json_encode($video), ENT_QUOTES, 'UTF-8'); ?>'>
                                            <button type="submit" name="add_to_playlist" 
                                                    class="add-btn <?php echo isset($_SESSION['playlist'][$video['id']]) ? 'added' : ''; ?>">
                                                <?php echo isset($_SESSION['playlist'][$video['id']]) ? '✓ Agregado' : '+ Agregar a lista'; ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <p>😕 No se encontraron videos disponibles para tu búsqueda.</p>
                            <p>Intenta con otros términos de búsqueda.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- PLAYLIST SIDEBAR -->
            <div class="playlist-sidebar">
                <div class="playlist-header">
                    <span class="playlist-title">Mi Lista</span>
                    <span class="playlist-count"><?php echo $playlistCount; ?></span>
                </div>
                
                <?php if ($playlistCount > 0): ?>
                    <form method="post" style="margin: 0;">
                        <button type="submit" name="clear_playlist" class="clear-btn" 
                                onclick="return confirm('¿Seguro que deseas limpiar toda la lista?')">
                            🗑️ Limpiar lista
                        </button>
                    </form>
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
                                <form method="post" style="margin: 0;" onclick="event.stopPropagation();">
                                    <input type="hidden" name="video_id" value="<?php echo htmlspecialchars($video['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" name="remove_from_playlist" class="remove-btn">✕</button>
                                </form>
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
        const playlist = <?php echo json_encode($playlistArray); ?>;
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
            // Video terminó (estado 0)
            if (event.data === YT.PlayerState.ENDED) {
                playNext();
            }
        }
        
        // Reproducir siguiente
        function playNext() {
            if (currentIndex < playlist.length - 1) {
                playVideo(currentIndex + 1);
            } else {
                // Fin de la lista, volver al inicio
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
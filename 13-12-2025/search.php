<?php
require 'db.php';

// Si no hay búsqueda, inicializamos vacío
if (!isset($_GET['q'])) {
    $_GET['q'] = "";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Mini YouTube</title>
  <style>
    body { font-family: Arial, sans-serif; }
    #resultados { display: flex; flex-wrap: wrap; gap: 20px; }
    .video { width: 300px; }
    iframe { width: 100%; height: 180px; }
  </style>
</head>
<body>
  <h1>Mini YouTube</h1>
  <!-- Barra de búsqueda siempre visible -->
  <form action="search.php" method="get">
    <input type="text" name="q" placeholder="Buscar videos..." style="width:300px;"
           value="<?php echo htmlspecialchars($_GET['q']); ?>">
    <button type="submit">Buscar</button>
  </form>
  <hr>

<?php
if (!empty($_GET['q'])) {
    $q = urlencode($_GET['q']);
    $apiKey = "AIzaSyD3ZReSXjU46YTwdMmJncwuG0t2efZsqY8"; // Reemplaza con tu clave válida
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&type=video&videoEmbeddable=true&maxResults=30&q={$q}&key={$apiKey}";

    // Usar cURL para mayor compatibilidad
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        die("Error en la conexión: " . curl_error($ch));
    }
    curl_close($ch);

    $data = json_decode($response, true);

    if ($data && isset($data['items'])) {
        echo "<h2>Resultados para: " . htmlspecialchars($_GET['q']) . "</h2>";
        echo "<div id='resultados'>";

        foreach ($data['items'] as $item) {
            if (isset($item['id']['videoId'])) {
                $videoId = $item['id']['videoId'];
                $title = htmlspecialchars($item['snippet']['title']);
                $publishedAt = $item['snippet']['publishedAt'];
                $channelTitle = htmlspecialchars($item['snippet']['channelTitle']);

                // Guardar en MySQL
                $sql = "INSERT INTO videos (videoId, title, publishedAt, channelTitle, searchQuery) 
                        VALUES ('$videoId', '$title', '$publishedAt', '$channelTitle', '{$_GET['q']}')";
                $conn->query($sql);

                // Mostrar video enmascarado
                echo "<div class='video'>
                        <h4>{$title}</h4>
                        <p><small>Canal: {$channelTitle}</small></p>
                        <iframe src='watch.php?v={$videoId}' frameborder='0' allowfullscreen></iframe>
                      </div>";
            }
        }
        echo "</div>";
    } else {
        echo "No se encontraron resultados o hubo un error en la API.";
    }
}
?>
</body>
</html>


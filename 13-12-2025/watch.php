<?php
if (isset($_GET['v'])) {
    $videoId = htmlspecialchars($_GET['v']);
    header("Location: https://www.youtube.com/embed/" . $videoId);
    exit;
} else {
    echo "Video no especificado.";
}
?>
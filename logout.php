<?php
session_start();
session_destroy();
header('Location: index.php');
exit();

?>
<script>
    // Refresh immediately when logout page loads
    window.location.reload(true);
</script>

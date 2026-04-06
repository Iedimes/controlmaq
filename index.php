<?php
/**
 * ControlMaq - Control de Alquiler de Máquinas
 */
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/config.php';

class Auth {
    public static function login($id, $nom, $rol) {
        $_SESSION['usuario_id'] = $id;
        $_SESSION['nombre'] = $nom;
        $_SESSION['rol'] = $rol;
    }
    public static function estaLogueado() {
        return isset($_SESSION['usuario_id']);
    }
    public static function logout() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            session_unset();
            session_destroy();
        }
        header("Location: ./");
        exit;
    }
}

if (isset($_GET['logout'])) { Auth::logout(); }

$login_error = "";
$request_method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
if ($request_method === 'POST' && isset($_POST['user_login'])) {
    $user = trim($_POST['user_login']);
    $pass = $_POST['password'];
    
    if (!$user || !$pass) {
        $login_error = "Usuario y contraseña requeridos";
    } else {
        $st = $pdo->prepare("SELECT * FROM usuarios WHERE user_login = ? LIMIT 1");
        $st->execute([$user]);
        $u = $st->fetch();
        
        if ($u && password_verify($pass, $u['password_hash'])) {
            Auth::login($u['id'], $u['nombre'], $u['rol']);
            
            // Registrar asistencia
            $st = $pdo->prepare("INSERT INTO asistencia (empleado_id, fecha, presente, login_hora) VALUES (?, CURDATE(), 1, CURTIME()) ON DUPLICATE KEY UPDATE presente = 1, login_hora = CURTIME()");
            $st->execute([$u['id']]);
            
            if ($u['rol'] === 'admin') {
                header("Location: panel.php");
            } else {
                header("Location: ./");
            }
            exit;
        } elseif (!$u) {
            // Auto-registrar usuario nuevo
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $nombre = ucfirst($user); // Usar el login como nombre
            $st = $pdo->prepare("INSERT INTO usuarios (nombre, user_login, password_hash, rol) VALUES (?, ?, ?, 'empleado')");
            $st->execute([$nombre, $user, $hash]);
            
            $nuevo_id = $pdo->lastInsertId();
            Auth::login($nuevo_id, $nombre, 'empleado');
            header("Location: ./");
            exit;
        } else {
            $login_error = "Credenciales incorrectas";
        }
    }
}

$logueado = Auth::estaLogueado();
$es_admin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
$nombre = $_SESSION['nombre'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ControlMaq</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #25d366; --bg-dark: #0b141a; --header: #202c33; --msg-in: #202c33; --msg-out: #005c4b; --text: #e9edef; }
        * { box-sizing: border-box; font-family: 'Outfit', sans-serif; -webkit-tap-highlight-color: transparent; }
        
        html, body { 
            height: 100%; min-height: 100vh;
            margin: 0; padding: 0; background: var(--bg-dark); color: var(--text);
        }
        
        #login-overlay { 
            position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 10000; 
            display: <?php echo $logueado ? 'none' : 'flex'; ?>; 
            align-items: center; justify-content: center; padding: 20px;
        }
        .login-card { background: #111b21; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 320px; text-align: center; border: 1px solid #2a3942; }
        .login-card input { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #333; background: #2a3942; color: white; border-radius: 8px; font-size: 16px; }
        .login-card button { width: 100%; padding: 12px; background: var(--primary); border: none; font-weight: 600; cursor: pointer; border-radius: 8px; font-size: 1rem; }
        
        header { 
            background: var(--header); padding: 12px 15px; 
            display: flex; align-items: center; justify-content: space-between; 
            border-bottom: 1px solid #333;
        }
        
        #chat { 
            height: calc(100vh - 130px); overflow-y: auto; padding: 15px; 
            display: flex; flex-direction: column; gap: 10px; 
        }
        .msg { padding: 10px 14px; border-radius: 10px; max-width: 85%; font-size: 0.9rem; word-wrap: break-word; }
        .in { background: var(--msg-in); align-self: flex-start; }
        .out { background: var(--msg-out); align-self: flex-end; }
        
        .footer-bar { 
            background: var(--header); padding: 10px 12px; 
            display: flex; gap: 10px; align-items: center;
        }
        .input-wrap { flex: 1; background: #2a3942; border-radius: 25px; padding: 2px 15px; display: flex; align-items: center; }
        #msg-input { flex: 1; background: transparent; border: none; color: white; padding: 10px; outline: none; font-size: 16px; }
        .btn-send { width: 42px; height: 42px; background: var(--primary); color: #0b141a; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        
        @media (max-width: 600px) {
            header { padding: 10px 12px; }
            #chat { height: calc(100vh - 120px); padding: 12px 10px; }
            .msg { font-size: 0.85rem; padding: 8px 12px; max-width: 90%; }
            .footer-bar { padding: 8px 10px; gap: 8px; }
            #msg-input { padding: 8px; font-size: 15px; }
            .btn-send { width: 40px; height: 40px; }
            .login-card { padding: 1.5rem; }
            .login-card input { padding: 10px; font-size: 15px; }
            .login-card button { padding: 10px; }
        }
        
        @media (max-width: 400px) {
            header { flex-wrap: wrap; gap: 8px; justify-content: center; }
            header > div:first-child { width: 100%; text-align: center; margin-bottom: 5px; }
            #chat { height: calc(100vh - 140px); }
            .msg { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <div id="login-overlay">
        <form class="login-card" method="POST">
            <i class="fas fa-tractor" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 10px;"></i>
            <h2 style="color:var(--primary); margin: 0 0 15px 0;">ControlMaq</h2>
            <?php if ($login_error): ?><div style="color:#ff5e5e; font-size:0.8rem; margin-bottom:10px;"><?php echo $login_error; ?></div><?php endif; ?>
            <input name="user_login" placeholder="Usuario" required autocomplete="username">
            <input name="password" type="password" placeholder="Contraseña" required autocomplete="current-password">
            <button type="submit">INGRESAR</button>
        </form>
    </div>

    <?php if ($logueado): ?>
    <header>
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:38px; height:38px; background:#008069; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:600;"><?php echo strtoupper(substr($nombre, 0, 2)); ?></div>
            <div><div style="font-weight:600; font-size:0.95rem;"><?php echo htmlspecialchars($nombre); ?></div><div style="color:var(--primary); font-size:0.75rem;"><?php echo $es_admin ? 'Admin' : 'Empleado'; ?></div></div>
        </div>
        <div style="display:flex; align-items:center; gap:15px;">
            <?php if ($es_admin): ?>
            <a href="panel.php" style="color:var(--primary); font-size:1.2rem;"><i class="fas fa-th-large"></i></a>
            <?php endif; ?>
            <a href="?logout=1" style="color:#8696a0;"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </header>

    <div id="chat">
        <?php if ($es_admin): ?>
        <div class="msg in">
            ¡Hola <?php echo htmlspecialchars(explode(' ', $nombre)[0]); ?>! 👋<br><br>
            Comandos disponibles:<br>
            • <i>"Resumen" - Balance de hoy</i><br>
            • <i>"Mis trabajos" - Ver trabajos de hoy</i>
        </div>
        <?php else: ?>
        <div class="msg in">
            ¡Hola <?php echo htmlspecialchars(explode(' ', $nombre)[0]); ?>! 👋<br><br>
            Registrá tus actividades:<br>
            • <i>"Trabajé 8 horas"</i> - Registrar horas<br>
            • <i>"Gasté 200000"</i> - Registrar gasto<br>
            • <i>"Cargué 300 litros"</i> - Cargar combustible<br>
            • <i>"Llovió hoy"</i> - Registrar lluvia<br>
            • <i>"No trabajé"</i> - Registrar ausencia<br>
            • <i>"Resumen"</i> - Ver balance del día<br>
            • 📷 - Enviar foto del horómetro
        </div>
        <?php endif; ?>
    </div>

    <div class="footer-bar">
        <button id="voice-btn" class="btn-send" style="background:none; color:var(--primary); font-size:1.4rem;"><i class="fas fa-microphone"></i></button>
        <label for="img-input" style="color:var(--primary); font-size:1.2rem; cursor:pointer;"><i class="fas fa-camera"></i></label>
        <input type="file" id="img-input" accept="image/*" style="display:none;" onchange="sendImage()">
        <div class="input-wrap">
            <input id="msg-input" placeholder="Escribe un mensaje..." onkeypress="if(event.key==='Enter') send()">
        </div>
        <button onclick="send()" class="btn-send"><i class="fas fa-paper-plane"></i></button>
    </div>

    <script>
        const msgInput = document.getElementById('msg-input');
        const voiceBtn = document.getElementById('voice-btn');

        const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (Recognition) {
            const rec = new Recognition();
            rec.lang = 'es-ES';
            rec.continuous = false;

            voiceBtn.onclick = () => {
                rec.start();
                voiceBtn.style.color = '#ff5e5e';
            };

            rec.onresult = (e) => {
                msgInput.value = e.results[0][0].transcript;
                send();
            };

            rec.onend = () => {
                voiceBtn.style.color = 'var(--primary)';
            };
        } else {
            voiceBtn.style.display = 'none';
        }

        async function send() {
            const i = document.getElementById('msg-input'), t = i.value.trim(); 
            if(!t) return;
            i.value = ''; 
            add(t, 'out');
            try {
                const r = await fetch('api/webhook.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ entry: [{ changes: [{ value: { messages: [{ text: { body: t } }] } }] }] })
                });
                const d = await r.json(); 
                add(d.mensaje || d.error || 'Error', 'in');
            } catch (e) { 
                add("Error de servidor", "in"); 
            }
        }
        function add(t, c, isHtml = false, isImage = false) {
            const d = document.getElementById('chat'), m = document.createElement('div');
            m.className = 'msg ' + c; 
            if (isImage) {
                m.innerHTML = '<img src="' + t + '" onclick="openImage(this.src)" style="max-width:200px;max-height:200px;border-radius:8px;cursor:pointer;">';
            } else if (isHtml) {
                m.innerHTML = t; 
            } else {
                m.innerText = t;
            }
            d.appendChild(m); 
            d.scrollTop = d.scrollHeight;
        }
        
        function openImage(src) {
            const modal = document.createElement('div');
            modal.style = 'position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;';
            modal.innerHTML = '<div style="position:absolute;top:10px;right:20px;color:white;font-size:30px;cursor:pointer;" onclick="this.parentElement.remove()">✕</div><img src="' + src + '" style="max-width:100%;max-height:100%;object-fit:contain;border-radius:8px;">';
            document.body.appendChild(modal);
        }
        
        async function sendImage() {
            const input = document.getElementById('img-input');
            const file = input.files[0];
            if (!file) return;
            
            // Show preview immediately (like WhatsApp)
            const reader = new FileReader();
            reader.onload = function(e) {
                add(e.target.result, 'out', false, true);
            };
            reader.readAsDataURL(file);
            
            const formData = new FormData();
            formData.append('imagen', file);
            formData.append('type', 'image');
            
            try {
                const r = await fetch('api/webhook.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                const d = await r.json();
                if (d.imagen) {
                    // Replace the preview with actual uploaded image
                    const msgs = document.querySelectorAll('.msg.out img');
                    if (msgs.length > 0) {
                        msgs[msgs.length - 1].src = d.imagen;
                    }
                }
                add(d.mensaje || d.error || 'Error', 'in', true);
            } catch (e) {
                add("Error al procesar imagen", 'in');
            }
            input.value = '';
        }
    </script>
    <?php endif; ?>
</body>
</html>

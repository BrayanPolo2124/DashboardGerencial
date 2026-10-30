<?php
$config = file_exists(__DIR__ . '/../../../.vynet_config.php') ? include(__DIR__ . '/../../../.vynet_config.php') : ['hospital_name' => 'Plataforma Gerencial', 'theme_color' => 'oscuro'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso - <?= htmlspecialchars($config['hospital_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-corp {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        }
    </style>
</head>
<body class="bg-slate-950 flex items-center justify-center min-h-screen">
    <div class="glass-corp p-10 rounded-3xl w-full max-w-md relative overflow-hidden border-t-2 border-indigo-500">
        <div class="text-center mb-8">
            <div class="bg-indigo-600 text-white w-16 h-16 mx-auto flex items-center justify-center rounded-2xl shadow-lg mb-4">
                <i class="fa-solid fa-hospital-user text-3xl"></i>
            </div>
            <h2 class="text-xl font-bold text-white"><?= htmlspecialchars($config['hospital_name']) ?></h2>
            <p class="text-xs text-indigo-400 mt-1 uppercase tracking-widest font-extrabold">Panel Gerencial Analítico</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-950/80 text-red-300 p-3 rounded-xl text-sm mb-4 text-center border border-red-500/30 font-semibold">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="route" value="login">
            <div>
                <label class="block text-xs font-black text-slate-400 uppercase mb-1">Usuario</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="username" required class="w-full bg-slate-900 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-white text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-black text-slate-400 uppercase mb-1">Contraseña</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" required class="w-full bg-slate-900 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-white text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3.5 rounded-xl transition shadow-lg text-sm mt-2 uppercase tracking-wider">
                Iniciar Sesión
            </button>
        </form>
    </div>
</body>
</html>
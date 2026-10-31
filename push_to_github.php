<?php
/**
 * BengalSeva - 1-Click Mobile GitHub Pusher (Fixed Author & Identity)
 */
declare(strict_types=1);

$output = [];
$status = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['github_username'] ?? '');
    $token = trim($_POST['github_token'] ?? '');
    $repo = trim($_POST['github_repo'] ?? '');
    $branch = trim($_POST['github_branch'] ?? 'main');

    if ($username && $token && $repo) {
        $dir = __DIR__;
        
        // Exclude huge backup files to prevent GitHub 100MB limit block
        $gitignorePath = $dir . '/.gitignore';
        if (!file_exists($gitignorePath)) {
            file_put_contents($gitignorePath, "backups/*.sql\n*.log\n.DS_Store\n");
        }

        $repoUrl = "https://{$username}:{$token}@github.com/{$username}/{$repo}.git";
        $email = 'admin@bengalseva.in';

        // Direct Author Identity Injection (Fixes Hostinger Permission Error)
        $commands = [
            "cd {$dir} && git init 2>&1",
            "cd {$dir} && git config user.name '{$username}' 2>&1",
            "cd {$dir} && git config user.email '{$email}' 2>&1",
            "cd {$dir} && git add -A 2>&1",
            "cd {$dir} && git -c user.name='{$username}' -c user.email='{$email}' commit -m 'BengalSeva Full Project Release' 2>&1",
            "cd {$dir} && git branch -M {$branch} 2>&1",
            "cd {$dir} && git remote remove origin 2>&1",
            "cd {$dir} && git remote add origin {$repoUrl} 2>&1",
            "cd {$dir} && git push -u origin {$branch} --force 2>&1"
        ];

        foreach ($commands as $cmd) {
            exec($cmd, $output);
        }
        $status = 'completed';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile GitHub Pusher (Fixed)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-4">
<div class="container">
    <div class="card border-0 shadow rounded-4 p-4 mx-auto bg-white" style="max-width: 500px;">
        <h4 class="fw-bold text-dark mb-1">🚀 Mobile GitHub Pusher</h4>
        <p class="text-muted small mb-4">Push entire BengalSeva codebase to GitHub from your phone.</p>

        <?php if ($status === 'completed'): ?>
            <div class="alert alert-success">
                <strong>Execution Finished!</strong> Check terminal output below.
            </div>
            <pre class="bg-dark text-white p-3 rounded-3 small" style="max-height: 250px; overflow-y:auto;"><?= htmlspecialchars(implode("\n", $output)) ?></pre>
            <div class="d-flex gap-2 mt-3">
                <a href="https://github.com/<?= htmlspecialchars($username) ?>/<?= htmlspecialchars($repo) ?>" target="_blank" class="btn btn-success fw-bold w-100">Open GitHub Repository &nearr;</a>
                <a href="push_to_github.php" class="btn btn-outline-secondary">Retry</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">GitHub Username</label>
                    <input type="text" name="github_username" class="form-control" value="skdj324" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Repository Name</label>
                    <input type="text" name="github_repo" class="form-control" value="bengalseva" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Personal Access Token</label>
                    <input type="password" name="github_token" class="form-control" placeholder="ghp_xxxxxxxxxxxx" required>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-semibold">Branch Name</label>
                    <input type="text" name="github_branch" class="form-control" value="main">
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3">
                    Push All Code to GitHub &rarr;
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
# Team Onboarding — Deploy Access

Anyone on the team can deploy to **https://hexugo.com** in one command after this 2-minute setup.

## What you need from the team lead

- The file **`hexugo_deploy`** (the private SSH key — kept in the team password vault, NEVER in git)

## Step 1 — place the key

### Linux / macOS / Git Bash on Windows

```bash
mkdir -p ~/.ssh
cp /path/to/hexugo_deploy ~/.ssh/hexugo_deploy
chmod 600 ~/.ssh/hexugo_deploy
```

### Windows (CMD or PowerShell)

```cmd
mkdir %USERPROFILE%\.ssh 2>nul
copy "C:\path\to\hexugo_deploy" "%USERPROFILE%\.ssh\hexugo_deploy"
```

(Windows OpenSSH uses ACLs instead of `chmod` — the default ACL after `copy` is fine for OpenSSH.)

## Step 2 — clone the repo

```bash
git clone https://github.com/musamakhizr/Procurement-Full-Project.git
cd Procurement-Full-Project
```

## Step 3 — deploy

After making changes locally and pushing to `main` on GitHub:

| Your terminal | Command |
|---|---|
| **Git Bash / Linux / macOS** | `bash deploy/deploy-now.sh` |
| **Windows CMD** | `deploy\deploy-now.bat` |
| **Windows PowerShell** | `.\deploy\deploy-now.ps1` |

That's it. The script:
1. SSHes to `deploy@47.86.173.194:2222` using your local key
2. On the server: pulls latest from main, rebuilds composer/npm if needed, runs migrations if any, refreshes caches, reloads PHP-FPM, restarts queue workers
3. Streams output back to your terminal in real time
4. Disconnects when done (~10 seconds for typical deploys)

## Optional flags

| What | Bash / macOS | Windows CMD | PowerShell |
|---|---|---|---|
| Different branch | `BRANCH=hotfix/x bash deploy/deploy-now.sh` | `set BRANCH=hotfix/x && deploy\deploy-now.bat` | `.\deploy\deploy-now.ps1 -Branch hotfix/x` |
| Different key location | `KEY=/path/to/key bash deploy/deploy-now.sh` | `set KEY=C:\path\to\key && deploy\deploy-now.bat` | `.\deploy\deploy-now.ps1 -Key C:\path\to\key` |

## Troubleshooting

### `Permission denied (publickey)`
- Verify the key is at `~/.ssh/hexugo_deploy` (or `%USERPROFILE%\.ssh\hexugo_deploy` on Windows)
- On Linux/macOS check perms: `ls -l ~/.ssh/hexugo_deploy` should show `-rw-------`
- If perms are wrong: `chmod 600 ~/.ssh/hexugo_deploy`

### `Could not resolve hostname hexugo-deploy: No such host is known`
You're using the old SSH alias which doesn't exist on your machine. Use the wrapper scripts above (`deploy-now.sh`/`.bat`/`.ps1`) instead — they use the IP directly.

### `Connection timed out`
Cloud security group might be blocking your IP. Contact the team lead to check.

### `bash: not found` on Windows
You're running the `.sh` script from CMD or PowerShell. Use `deploy-now.bat` (CMD) or `deploy-now.ps1` (PowerShell) instead.

### "Host key verification failed"
The server's identity changed (rare). Remove the old entry:
```bash
# Linux/macOS/Git-Bash
ssh-keygen -R "[47.86.173.194]:2222"

# Windows CMD
ssh-keygen -R "[47.86.173.194]:2222"
```
Then run the deploy script again — it'll prompt to accept the new host key.

## Where the keys/passwords live

See `production-doc/12-credentials.md` in the repo for the full inventory of where each secret is stored.

## Direct-command fallback (without any wrapper script)

If the wrapper scripts don't work on your shell, this raw command always does the same thing:

```bash
ssh -i ~/.ssh/hexugo_deploy -p 2222 deploy@47.86.173.194 "bash /opt/hexugo-deploy/deploy/scripts/quick-deploy.sh"
```

(Windows CMD: replace `~/` with `%USERPROFILE%\`.)

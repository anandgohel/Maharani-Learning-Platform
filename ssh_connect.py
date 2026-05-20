"""SSH connection test for learn.maharaniweddings.com on SiteGround"""
import paramiko
import sys

KEY_FILE = r'c:\Users\misc\.gemini\antigravity\scratch\LearnDash_MW\.ssh\siteground_learn'
HOSTNAME = 'gcam1167.siteground.biz'
USERNAME = 'u2443-07wpuscc7fjl'
PORT = 18765
PASSPHRASE = 'FREEDOM@ME1!'

def run_ssh_command(command):
    """Connect via SSH and run a command, return output."""
    key = paramiko.Ed25519Key.from_private_key_file(KEY_FILE, password=PASSPHRASE)
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOSTNAME, port=PORT, username=USERNAME, pkey=key)
    stdin, stdout, stderr = client.exec_command(command)
    output = stdout.read().decode()
    error = stderr.read().decode()
    client.close()
    return output, error

if __name__ == '__main__':
    cmd = ' '.join(sys.argv[1:]) if len(sys.argv) > 1 else 'echo "Connected!" && pwd && ls -la'
    print(f"Running: {cmd}")
    out, err = run_ssh_command(cmd)
    if out:
        print(out)
    if err:
        print(f"STDERR: {err}", file=sys.stderr)

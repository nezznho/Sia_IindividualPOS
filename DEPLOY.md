# Deploying GreenCart POS to AWS EC2

Runs as two Docker containers: `app` (PHP 8.3 + Apache, port 80) and `db` (MySQL 8.0).
Tested on Amazon Linux 2023, t3.micro (1 GB RAM + 2 GB swap).

## 1. Connect

```powershell
ssh -i "$env:USERPROFILE\Downloads\Triangle_POS.pem" ec2-user@<PUBLIC_IP>
```

Amazon Linux uses the user `ec2-user` (Ubuntu images use `ubuntu`).
The instance's security group must allow inbound **TCP 22** (SSH) and **TCP 80** (HTTP).

## 2. One-time server setup

```bash
# 2 GB swap (needed on 1 GB instances)
sudo dd if=/dev/zero of=/swapfile bs=1M count=2048 && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo "/swapfile swap swap defaults 0 0" | sudo tee -a /etc/fstab

# Docker + git
sudo dnf install -y docker git
sudo systemctl enable --now docker
sudo usermod -aG docker ec2-user

# Docker Compose + Buildx plugins
sudo mkdir -p /usr/local/lib/docker/cli-plugins
sudo curl -fsSL https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64 \
  -o /usr/local/lib/docker/cli-plugins/docker-compose
# buildx: download the latest linux-amd64 binary from https://github.com/docker/buildx/releases
# to /usr/local/lib/docker/cli-plugins/docker-buildx
sudo chmod +x /usr/local/lib/docker/cli-plugins/*
```

## 3. Get the code and configure

```bash
git clone https://github.com/nezznho/Sia_IindividualPOS.git greencart-pos
cd greencart-pos
cp .env.production.example .env
sed -i "s#^APP_KEY=.*#APP_KEY=base64:$(openssl rand -base64 32)#" .env
sed -i "s#^DB_PASSWORD=.*#DB_PASSWORD=$(openssl rand -hex 16)#" .env
sed -i "s#^DB_ROOT_PASSWORD=.*#DB_ROOT_PASSWORD=$(openssl rand -hex 16)#" .env
nano .env   # set APP_URL=http://<PUBLIC_IP>
```

## 4. Start

```bash
sudo docker compose -f docker-compose.prod.yml up -d --build
```

The first start runs migrations and seeds the default admin:
**super.admin@test.com / 12345678** — change this password right after logging in.

Open `http://<PUBLIC_IP>` in a browser.

## Updating after a new `git push`

```bash
cd ~/greencart-pos
git pull
sudo docker compose -f docker-compose.prod.yml up -d --build
```

Data (database and uploaded images) lives in Docker volumes and survives rebuilds.

## Useful commands

```bash
sudo docker compose -f docker-compose.prod.yml ps          # status
sudo docker compose -f docker-compose.prod.yml logs -f app # app logs
sudo docker compose -f docker-compose.prod.yml exec app php artisan <command>
sudo docker compose -f docker-compose.prod.yml down        # stop (keeps data)
```

Do **not** run `down -v` — that deletes the database volume.

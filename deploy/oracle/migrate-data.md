# Migrating data from Render to the Oracle VM

Your data lives on Render's persistent disk at `/var/www/html/storage`
(SQLite DB + HLS transcodes + uploaded media). Render's web services can't be
SSH'd into directly, but they have an interactive **Shell** in the dashboard.
We use it to package `storage` into a tar, download it, and load it onto the
Oracle volume.

## 1. Export from Render

1. Render dashboard → **krettel-app** service → **Shell** tab.
2. In the shell run:

   ```bash
   cd /var/www/html
   tar -czf /tmp/krettel-storage.tar.gz storage
   cp /tmp/krettel-storage.tar.gz public/krettel-storage.tar.gz
   ```

3. Download it from the browser:

   ```bash
   # your machine, from the repo root
   curl -o krettel-storage.tar.gz "https://<YOUR-APP>.onrender.com/krettel-storage.tar.gz"
   ```

4. **Delete it from the server immediately** (it's now public):

   ```bash
   rm -f /var/www/html/public/krettel-storage.tar.gz
   ```

## 2. Upload to the Oracle VM

```bash
scp -i your-key.pem krettel-storage.tar.gz ubuntu@<PUBLIC_IP>:~
```

## 3. Load onto the storage volume

Run on the VM, from the repo root:

```bash
# unpack into a staging dir
mkdir -p ~/krettel-data
tar -xzf ~/krettel-storage.tar.gz -C ~/krettel-data   # yields ~/krettel-data/storage

# the app may already be running — stop it so it isn't writing
docker compose -f deploy/oracle/docker-compose.yml stop app

# copy the files into the named volume (33 = www-data in the php image)
docker run --rm \
  -v krettel_krettel-storage:/mnt \
  -v ~/krettel-data:/data \
  alpine sh -c "cp -a /data/storage/. /mnt/ && chown -R 33:33 /mnt"

# restart
docker compose -f deploy/oracle/docker-compose.yml start app
```

> The volume name `krettel_krettel-storage` comes from the top-level
> `name: krettel` in `docker-compose.yml`. If you renamed the project, adjust
> it — check with `docker volume ls`.

## 4. Verify

```bash
docker compose -f deploy/oracle/docker-compose.yml logs -f app
curl -I https://<SITE_DOMAIN>/login
```

Then switch your domain's DNS A/AAAA record to the Oracle VM and shut down the
Render service to avoid double-billing bandwidth.

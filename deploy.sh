#!/bash/bin

git reset --hard master
git pull origin master
npm install
composer install
gulp build
gulp production
php bin/console db:migrate
sh cache_clear.sh

sleep 10 && chmod 777 -R /var/www/gogocarto/var/ &
sleep 60 && chmod 777 -R /var/www/gogocarto/var/ &
sleep 120 && chmod 777 -R /var/www/gogocarto/var/ &
sleep 300 && chmod 777 -R /var/www/gogocarto/var/ &
sleep 600 && chmod 777 -R /var/www/gogocarto/var/ &
sleep 2000 && chmod 777 -R /var/www/gogocarto/var/ &
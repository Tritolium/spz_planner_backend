scp -r ./api pi@buzzer.dns.army:/home/pi/build/ && ssh pi@buzzer.dns.army 'sudo cp -r -u /home/pi/build/api /var/www/html/'
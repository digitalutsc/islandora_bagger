
# import the smtplib module. It should be included in Python by default
import smtplib
import zipfile
import tempfile
import sys
import yaml
from email import encoders
from email.message import Message
from email.mime.base import MIMEBase
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText

with open(sys.argv[3]) as f:
    yml = yaml.safe_load(f)
name = yml['bag-info']['Contact-Name']
email = yml['bag-info']['Contact-Email']
# set up the SMTP server
s = smtplib.SMTP(host='smtp.gmail.com', port=587)
s.starttls()
s.login('islandora.bagger@gmail.com','xygKyz-2kapne-gifdig')
msg = MIMEMultipart()
message = 'Hi ' + name +',\nHere is your bag for node ' + sys.argv[1] + ',\nThank you for using the Islandora Lite Bagger'
msg['From']='islandora.bagger@gmail.com'
msg['To']=email
msg['Subject']='Bag for node ' + sys.argv[1]
sub = MIMEBase('application', 'zip')
zf = open(sys.argv[2], "rb")
sub.set_payload(zf.read())
encoders.encode_base64(sub)
filename = sys.argv[1] + '.zip'
sub.add_header('Content-Disposition', "attachment; filename= %s" % filename)
msg.attach(sub)
msg.attach(MIMEText(message, 'plain'))
s.sendmail('islandora.bagger@gmail.com', email, msg.as_string())
s.quit()
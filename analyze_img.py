from PIL import Image
im = Image.open('C:/Users/Admin/.gemini/antigravity-ide/brain/58bf217d-e129-4372-9048-ea441e49d3f5/.tempmediaStorage/screen_check.png')
print('Image size:', im.size)
w, h = im.size
# Find border between appbar and content
# scan x=200, y from 150 to 450
for y in range(200, 380, 5):
    print(f'y={y}: {im.getpixel((200, y))[:3]}')

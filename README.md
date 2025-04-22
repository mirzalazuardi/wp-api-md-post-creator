# wp-api-md-post-creator

Add this to your wp-content/plugins

## Usage

```bash
curl -X POST \
  http://yourdomain/wp-json/markdown-post-creator/v1/upload-markdown \
  -u "username:password" \
  -F "markdown_file=@/your/path/to/example.md" \
  -F "title=My Post Title using markdown plugin"
# response if succeed
# {"status":"success","message":"Post created successfully.","post_id":14}
```

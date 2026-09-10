import sanitizeHtml from 'sanitize-html';

// CMS markup is untrusted when it is embedded in the storefront's origin.
export function safeArticleHtml(html) {
  return sanitizeHtml(typeof html === 'string' ? html : '', {
    allowedTags: [...sanitizeHtml.defaults.allowedTags, 'img'],
    allowedAttributes: {
      a: ['href', 'title'],
      img: ['src', 'alt', 'width', 'height', 'loading'],
      th: ['colspan', 'rowspan'], td: ['colspan', 'rowspan'],
    },
    allowedSchemes: ['https', 'http', 'mailto'],
    allowProtocolRelative: false,
  });
}

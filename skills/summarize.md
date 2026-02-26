---
description: Summarize content concisely
parameters:
  content:
    type: string
    description: The text to summarize
    required: true
  style:
    type: string
    description: Summary style
    enum: [brief, detailed, bullet-points]
    default: brief
requires_confirmation: false
---

Please summarize the following content in a $style format:

$content

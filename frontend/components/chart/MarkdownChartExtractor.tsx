"use client";

import React, { useEffect, useState, useRef, useMemo } from 'react';
import MermaidRenderer from './MermaidRenderer';
import ChartRenderer from './ChartRenderer';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import rehypeRaw from 'rehype-raw';

interface MarkdownChartExtractorProps {
  content: string;
  markdownComponents?: any;
}

export default function MarkdownChartExtractor({ content, markdownComponents = {} }: MarkdownChartExtractorProps) {
  const [mermaidDiagrams, setMermaidDiagrams] = useState<string[]>([]);
  const [chartConfigs, setChartConfigs] = useState<any[]>([]);

  // Phân tích nội dung MarkDown để trích xuất mã biểu đồ
  useEffect(() => {
    // Trích xuất mermaid diagrams
    const mermaidRegex = /```mermaid\n([\s\S]*?)```/g;
    const mermaidMatches = [...content.matchAll(mermaidRegex)];
    const mermaidDiagrams = mermaidMatches.map(match => match[1]);
    setMermaidDiagrams(mermaidDiagrams);

    // Trích xuất chart configs
    const chartRegex = /```chart\n([\s\S]*?)```/g;
    const chartMatches = [...content.matchAll(chartRegex)];
    const chartConfigs = chartMatches.map(match => {
      try {
        return JSON.parse(match[1]);
      } catch (e) {
        console.error('Lỗi phân tích cú pháp biểu đồ:', e);
        return null;
      }
    }).filter(Boolean);

    setChartConfigs(chartConfigs);
  }, [content]);

  // Xử lý nội dung để hiển thị
  const processedContent = useMemo(() => {
    // Loại bỏ các khối mã Mermaid và Chart từ nội dung
    return content
      .replace(/```mermaid\n[\s\S]*?```/g, '')
      .replace(/```chart\n[\s\S]*?```/g, '');
  }, [content]);

  return (
    <div className="markdown-chart-content">
      {/* Hiển thị nội dung Markdown */}
      <ReactMarkdown 
        remarkPlugins={[remarkGfm]}
        rehypePlugins={[rehypeRaw]}
        components={markdownComponents}
      >
        {processedContent}
      </ReactMarkdown>
      
      {/* Hiển thị các biểu đồ Mermaid */}
      {mermaidDiagrams.map((diagram, index) => (
        <div key={`mermaid-${index}`} className="mt-4 mb-4">
          <MermaidRenderer chart={diagram} />
        </div>
      ))}
      
      {/* Hiển thị các biểu đồ dữ liệu */}
      {chartConfigs.map((config, index) => (
        <div key={`chart-${index}`} className="mt-4 mb-4">
        <ChartRenderer 
            type={config.type || 'line'} 
            data={config.data} 
            x={config.x || 'x'} 
            y={config.y || 'y'} 
            title={config.title} 
        />
        </div>
      ))}
    </div>
  );
} 
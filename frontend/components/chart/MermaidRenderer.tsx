"use client";

import { useEffect, useRef } from 'react';
import mermaid from 'mermaid';

interface MermaidProps {
  chart: string;
}

export default function MermaidRenderer({ chart }: MermaidProps) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (ref.current) {
      mermaid.initialize({ 
        startOnLoad: true,
        theme: 'default',
        securityLevel: 'loose',
      });
      
      // Xóa bất kỳ render trước đó
      ref.current.innerHTML = chart;
      
      // Render biểu đồ
      mermaid.run({
        nodes: [ref.current]
      }).catch(error => {
        console.error("Lỗi khi render biểu đồ Mermaid:", error);
      });
    }
  }, [chart]);

  return <div className="mermaid" ref={ref}>{chart}</div>;
} 
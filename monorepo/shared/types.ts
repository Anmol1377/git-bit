// The file every slide keeps warning you about.
// frontend imports it, worker parses it, api generates from the same shape.
export type Event = {
  id: string;
  v: 2;                 // bump this and three services need a rebuild
  kind: 'click' | 'view' | 'purchase';
  at: number;
};

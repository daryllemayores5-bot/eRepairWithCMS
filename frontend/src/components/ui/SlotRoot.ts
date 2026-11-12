import { cloneVNode, Comment, Fragment, type FunctionalComponent, type VNode } from 'vue';
// Match the original asChild contract without adding an element around links.
export const SlotRoot: FunctionalComponent = (_, {
  attrs,
  slots
}) => {
  const flatten = (nodes: VNode[]): VNode[] => nodes.flatMap(n => n.type === Fragment ? flatten(n.children as VNode[]) : n.type === Comment ? [] : [n]);
  const children = flatten(slots.default?.() ?? []);
  if (children.length !== 1) throw new Error('Button as-child requires one element');
  return cloneVNode(children[0], attrs);
};
SlotRoot.inheritAttrs = false;
